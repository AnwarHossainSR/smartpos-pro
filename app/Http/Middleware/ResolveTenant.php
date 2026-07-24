<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the authenticated user's tenant into the TenantContext so that every
 * tenant-scoped query for the rest of the request is automatically isolated.
 */
class ResolveTenant
{
    public function __construct(protected TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->tenant_id && ! ($user->is_super_admin ?? false)) {
            $this->context->set($user->tenant_id);
        }

        return $next($request);
    }
}
