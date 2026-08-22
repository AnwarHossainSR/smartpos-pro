<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: `permission:products.create`. */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(response()->json([
                'message' => 'Unauthenticated.',
            ], 401));
        }

        if ($user->is_super_admin) {
            return $next($request);
        }

        if (! $user->hasPermission($permission)) {
            abort(response()->json([
                'message' => "Missing required permission: {$permission}.",
            ], 403));
        }

        return $next($request);
    }
}
