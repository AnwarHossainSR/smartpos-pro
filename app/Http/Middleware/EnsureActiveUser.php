<?php

namespace App\Http\Middleware;

use App\Domain\Enums\UserStatus;
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
 * the request cycle itself.
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

        if ($user && $this->statusOf($user) === UserStatus::Suspended) {
            return response()->json([
                'message' => 'This account has been suspended.',
                'code' => 'user_suspended',
            ], 403);
        }

        return $next($request);
    }

    /**
     * The user's status, or null when the model was hydrated without that column.
     *
     * Read from the raw attribute bag rather than through `$user->status`, because
     * `Model::shouldBeStrict()` is on outside production: a model built without the
     * column — `User::create()` leaving it to the database default, a partial
     * `select()` — throws `MissingAttributeException` on property access, which turns
     * every request by such a user into a 500 rather than a permission decision.
     *
     * An absent column means "we don't know", and unknown is not suspended: nothing
     * can be concluded from a column that was never read, and 403-ing on it would
     * lock out users over how their model happened to be loaded. Revoking tokens at
     * the point of suspension stays the primary defence; this is the backstop for
     * anything already in flight, and in production `$request->user()` is always a
     * fully hydrated model.
     */
    private function statusOf(mixed $user): ?UserStatus
    {
        $raw = $user->getAttributes()['status'] ?? null;

        if ($raw instanceof UserStatus) {
            return $raw;
        }

        return $raw === null ? null : UserStatus::tryFrom((string) $raw);
    }
}
