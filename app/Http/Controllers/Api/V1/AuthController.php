<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Requests\Auth\UpdatePreferencesRequest;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\TenantProvisioner;
use App\Services\Auth\TotpService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected const LOCKOUT_THRESHOLD = 5;

    protected const LOCKOUT_MAX_MINUTES = 60;

    protected const CHALLENGE_TTL_MINUTES = 5;

    public function __construct(
        protected TenantProvisioner $provisioner,
        protected AuditLogService $audit,
        protected TotpService $totp,
        protected TenantContext $context,
    ) {}

    /** Register a new business: creates the tenant, business, branch, warehouse and owner user. */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->provisioner->register($request->validated());

        $token = $this->issueToken($user, $request);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::withoutTenancy()->where('email', $request->email)->first();

        // Bind the tenant as soon as we know it, so every subsequent query in this request
        // (relation loads, token issuance, etc.) is naturally and correctly scoped.
        if ($user && ! ($user->is_super_admin ?? false)) {
            $this->context->set($user->tenant_id);
        }

        if ($user && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => ["Too many failed attempts. Try again after {$user->locked_until->diffForHumans()}."],
            ]);
        }

        if (! $user || ! Hash::check($request->password, $user->password)) {
            if ($user) {
                $this->registerFailedAttempt($user);
            }

            $this->audit->log('auth.login_failed', $user, null, ['email' => $request->email], $user, $user?->tenant_id);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === UserStatus::Suspended) {
            throw ValidationException::withMessages([
                'email' => ['This account is suspended.'],
            ]);
        }

        $this->guardTenantStatus($user);

        if ($user->hasEnabledTwoFactor()) {
            $challengeToken = Str::random(40);
            Cache::put("2fa_challenge:{$challengeToken}", $user->id, now()->addMinutes(self::CHALLENGE_TTL_MINUTES));

            return response()->json([
                'requires_2fa' => true,
                'challenge_token' => $challengeToken,
                'expires_in' => self::CHALLENGE_TTL_MINUTES * 60,
            ]);
        }

        return response()->json($this->completeLogin($user, $request));
    }

    /** Second step of login for accounts with 2FA enabled — code or a single-use recovery code. */
    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $userId = Cache::get("2fa_challenge:{$request->challenge_token}");

        abort_if(! $userId, 422, 'This login challenge has expired. Please sign in again.');

        $user = User::withoutTenancy()->findOrFail($userId);

        if (! ($user->is_super_admin ?? false)) {
            $this->context->set($user->tenant_id);
        }

        if ($user->isLocked()) {
            throw ValidationException::withMessages([
                'code' => ["Too many failed attempts. Try again after {$user->locked_until->diffForHumans()}."],
            ]);
        }

        $ok = $request->filled('recovery_code')
            ? $this->consumeRecoveryCode($user, $request->string('recovery_code'))
            : $this->totp->verify($user->two_factor_secret, (string) $request->input('code'));

        if (! $ok) {
            $this->registerFailedAttempt($user);
            $this->audit->log('auth.2fa_failed', $user, null, null, $user, $user->tenant_id);

            throw ValidationException::withMessages(['code' => ['Invalid code.']]);
        }

        Cache::forget("2fa_challenge:{$request->challenge_token}");

        return response()->json($this->completeLogin($user, $request));
    }

    /** @return array{user: UserResource, token: string} */
    protected function completeLogin(User $user, Request $request): array
    {
        $user->forceFill(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null])->save();
        $user->load(['roles', 'business']);

        $this->audit->log('auth.login', $user, null, null, $user, $user->tenant_id);

        return [
            'user' => new UserResource($user),
            'token' => $this->issueToken($user, $request),
        ];
    }

    protected function guardTenantStatus(User $user): void
    {
        if (($user->is_super_admin ?? false) || ! $user->tenant_id) {
            return;
        }

        $tenant = Tenant::find($user->tenant_id);

        if (! $tenant || ! $tenant->status->hasApiAccess()) {
            abort(response()->json([
                'message' => 'This account has been suspended.',
                'code' => 'tenant_suspended',
            ], 403));
        }
    }

    /** Progressive lockout: doubles each time the threshold is hit again, capped at an hour. */
    protected function registerFailedAttempt(User $user): void
    {
        $attempts = $user->failed_login_attempts + 1;
        $locked_until = $user->locked_until;

        if ($attempts >= self::LOCKOUT_THRESHOLD) {
            $tier = $attempts - self::LOCKOUT_THRESHOLD;
            $minutes = min(2 ** $tier, self::LOCKOUT_MAX_MINUTES);
            $locked_until = now()->addMinutes($minutes);
        }

        $user->forceFill(['failed_login_attempts' => $attempts, 'locked_until' => $locked_until])->save();
    }

    protected function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** Always responds the same way regardless of whether the email exists, to avoid leaking account existence. */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        // The password-reset broker resolves the user internally (framework code we don't
        // control the query of) on this public, pre-tenant-binding route.
        $this->context->runUnscoped(fn () => Password::sendResetLink($request->only('email')));

        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = $this->context->runUnscoped(fn () => Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));

                $this->audit->log('auth.password_reset', $user, null, null, $user, $user->tenant_id);
            },
        ));

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password reset. All existing sessions have been signed out.']);
    }

    /** Public, signed link — clicked directly from the verification email, no bearer token available. */
    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired verification link.');

        $user = User::withoutTenancy()->findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'Invalid verification link.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->audit->log('auth.email_verified', $user, null, null, $user, $user->tenant_id);
        }

        return redirect(rtrim((string) config('app.frontend_url'), '/').'/login?verified=1');
    }

    /** Verify email with OTP code — public endpoint for post-registration verification. */
    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = User::withoutTenancy()->where('email', $request->email)->first();

        abort_if(! $user, 422, 'Invalid verification code.');

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        if (! $user->verifyEmailWithOtp($request->code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        $this->audit->log('auth.email_verified', $user, null, null, $user, $user->tenant_id);

        return response()->json(['message' => 'Email verified successfully.']);
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification code sent.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles', 'business', 'branch'));
    }

    /**
     * Per-user UI preferences (currently just the theme). Scoped to the caller by
     * construction: it reads and writes `$request->user()` and takes no user id, so nobody
     * can reach another user's row through it. Deliberately carries **no** `permission:`
     * guard — every slug in PermissionCatalog gates a tenant-admin capability, and requiring
     * one here would stop a cashier picking a colour scheme on their own screen.
     *
     * Saved quietly: a cosmetic preference written on a debounce from the client would
     * otherwise flood the audit trail that exists for money/access-sensitive changes.
     */
    public function updatePreferences(UpdatePreferencesRequest $request): UserResource
    {
        $user = $request->user();

        $user->fill(['theme' => $request->validated('theme')])->saveQuietly();

        return new UserResource($user->load('roles', 'business', 'branch'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->string('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($request->string('new_password'))])->save();
        $user->tokens()->whereKeyNot($user->currentAccessToken()->id)->delete();

        $this->audit->log('auth.password_changed', $user, null, null, $user, $user->tenant_id);

        return response()->json(['message' => 'Password updated. Other sessions have been signed out.']);
    }

    public function twoFactorStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'enabled' => $user->hasEnabledTwoFactor(),
            'recovery_codes_remaining' => $user->hasEnabledTwoFactor()
                ? count($user->two_factor_recovery_codes ?? [])
                : 0,
        ]]);
    }

    public function lockoutStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'is_locked' => $user->isLocked(),
            'locked_until' => $user->locked_until?->toIso8601String(),
            'failed_attempts' => $user->failed_login_attempts ?? 0,
        ]]);
    }

    /** Active devices/sessions (personal access tokens) for the current user. */
    public function sessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        $sessions = $request->user()->tokens()
            ->orderByDesc('last_used_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'ip_address' => $token->ip_address,
                'last_used_at' => $token->last_used_at,
                'created_at' => $token->created_at,
                'expires_at' => $token->expires_at,
                'current' => $current && $token->id === $current->id,
            ]);

        return response()->json(['data' => $sessions]);
    }

    /** Revoke a single device/session — must belong to the current user. */
    public function revokeSession(Request $request, string $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($tokenId)->delete();

        abort_if($deleted === 0, 404, 'Session not found.');

        return response()->json(['message' => 'Session revoked.']);
    }

    /** Revoke every session except the one making this request. */
    public function revokeAllSessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();

        $request->user()->tokens()
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->delete();

        return response()->json(['message' => 'All other sessions revoked.']);
    }

    protected function issueToken(User $user, Request $request): string
    {
        $newToken = $user->createToken($request->input('device_name', 'web'), $this->abilitiesFor($user));

        $newToken->accessToken->forceFill(['ip_address' => $request->ip()])->save();

        return $newToken->plainTextToken;
    }

    /**
     * Token abilities mirror the user's current permissions at issuance time
     * (rather than a blanket '*') — owner/super-admin still get everything.
     *
     * @return string[]
     */
    protected function abilitiesFor(User $user): array
    {
        if (($user->is_super_admin ?? false) || $user->hasRole('owner')) {
            return ['*'];
        }

        return $user->permissionSlugs();
    }
}
