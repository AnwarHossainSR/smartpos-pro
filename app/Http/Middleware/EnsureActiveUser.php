<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects a request whose authenticated user has been suspended.
 *
 * Status used to be checked only in `AuthService::login()`, which meant suspending
 * someone blocked their next *sign-in* and nothing else — every token they already
 * held stayed valid for its full lifetime. Tokens are revoked at the point of
 * suspension now, but that alone is a race (a request already in flight, a token
 * minted between the read and the delete), so the check also has to exist here, on
 * the request cycle itself, where it fails closed.
 *
 * Account lockout (`locked_until`) is deliberately *not* enforced here: it counts
 * failed sign-in attempts, so applying it to live sessions would let anyone log a
 * user out by guessing their password badly enough.
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Block only on a status we positively read as suspended. `users.status` is NOT
        // NULL with an `active` default, so a null here is impossible — but `?->` would
        // turn one into a lockout for everybody, which is not a failure mode worth risking.
        if ($user && $user->status !== null && ! $user->status->hasApiAccess()) {
            return response()->json([
                'message' => 'This account has been suspended.',
                'code' => 'user_suspended',
            ], 403);
        }

        return $next($request);
    }
}
