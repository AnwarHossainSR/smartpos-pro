<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenant;
use App\Http\Middleware\ErrorTracking;
use App\Http\Middleware\Idempotency;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SuperAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

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
    })->create();
