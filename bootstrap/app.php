<?php

use App\Exceptions\DomainException;
use App\Exceptions\InsufficientStockException;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\ErrorTracking;
use App\Http\Middleware\Idempotency;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SuperAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\Exception\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // `withRouting(commands: ...)` only registers routes/console.php; class-based
    // commands under app/Console/Commands need their own path registration.
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'ensure.tenant' => EnsureTenant::class,
            'permission' => CheckPermission::class,
            'idempotency' => Idempotency::class,
            'super_admin' => SuperAdmin::class,
            'error-tracking' => ErrorTracking::class,
        ]);

        $middleware->throttleApi();
        $middleware->api(append: [SecurityHeaders::class]);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: ResolveTenant::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: EnsureTenant::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // --- Structured API error responses ---

        $exceptions->renderable(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->renderable(function (ModelNotFoundException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $model = class_basename($e->getModel());

            return response()->json([
                'message' => "{$model} not found.",
            ], 404);
        });

        $exceptions->renderable(function (AuthorizationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: 'Forbidden.',
            ], 403);
        });

        $exceptions->renderable(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        });

        $exceptions->renderable(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Endpoint not found.',
            ], 404);
        });

        $exceptions->renderable(function (MethodNotAllowedHttpException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Method not allowed.',
            ], 405);
        });

        $exceptions->renderable(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $retryAfter = $e->getResponse()->headers->get('Retry-After');

            return response()->json([
                'message' => 'Too many requests. Please slow down.',
            ], 429)->withHeaders([
                'Retry-After' => $retryAfter ?? 60,
            ]);
        });

        $exceptions->renderable(function (InsufficientStockException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'error' => [
                    'code' => 'insufficient_stock',
                    'product_id' => $e->productId,
                    'warehouse_id' => $e->warehouseId,
                    'available' => $e->available,
                    'requested' => $e->requested,
                ],
            ], 409);
        });

        $exceptions->renderable(function (QueryException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'A database error occurred. Please try again.',
            ], 500);
        });

        $exceptions->renderable(function (DomainException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return $e->render();
        });

        $exceptions->renderable(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'message' => 'Internal server error.',
            ], 500);
        });
    })->create();
