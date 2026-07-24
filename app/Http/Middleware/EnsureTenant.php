<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rejects requests that reach tenant-scoped routes without a resolved tenant. */
class EnsureTenant
{
    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->has()) {
            abort(response()->json([
                'message' => 'No active tenant for this request.',
            ], 403));
        }

        return $next($request);
    }
}
