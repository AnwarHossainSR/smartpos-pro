<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Rejects requests that reach tenant-scoped routes without a resolved, usable tenant. */
class EnsureTenant
{
    /** Tenant statuses that may still use the API. Anything else (suspended, cancelled, …) is blocked. */
    protected const USABLE_STATUSES = ['trialing', 'active'];

    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->context->has()) {
            abort(response()->json([
                'message' => 'No active tenant for this request.',
            ], 403));
        }

        $tenant = Tenant::find($this->context->id());

        if (! $tenant || ! in_array($tenant->status, self::USABLE_STATUSES, true)) {
            abort(response()->json([
                'message' => 'This account has been suspended.',
                'code' => 'tenant_suspended',
            ], 403));
        }

        return $next($request);
    }
}
