<?php

namespace App\Http\Middleware;

use App\Services\Platform\ModuleRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `module:accounting`.
 *
 * Module toggles used to be advisory — `GET /modules` told the SPA which navigation
 * to render and nothing checked them again, so a tenant whose accounting module the
 * platform had switched off could still call every accounting endpoint directly.
 * Gating that only hides menu items is not gating.
 *
 * Super admins pass through: they operate across tenants and need to be able to see
 * a module in order to turn it back on.
 */
class EnsureModuleEnabled
{
    public function __construct(private ModuleRegistry $modules) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if ($user?->is_super_admin) {
            return $next($request);
        }

        if (! $this->modules->enabled($module, $user?->tenant_id)) {
            return response()->json([
                'message' => 'This module is not enabled for your account.',
                'code' => 'module_disabled',
                'module' => $module,
            ], 403);
        }

        return $next($request);
    }
}
