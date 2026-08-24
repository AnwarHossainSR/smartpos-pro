<?php

namespace App\Services;

use App\Domain\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\TotpService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthService
{
    private const LOCKOUT_THRESHOLD = 5;

    private const LOCKOUT_MAX_MINUTES = 60;

    private const CHALLENGE_TTL_MINUTES = 5;

    public function __construct(
        private AuditLogService $audit,
        private TotpService $totp,
        private TenantContext $context,
    ) {}

    public function login(string $email, string $password, ?string $deviceName = 'web', ?string $ipAddress = null): array
    {
        $user = User::withoutTenancy()->where('email', $email)->first();

        if ($user && $user->tenant_id) {
            $this->context->set($user->tenant_id);
        }

        if ($user && $user->isLocked()) {
            throw ValidationException::withMessages([
                'email' => ["Too many failed attempts. Try again after {$user->locked_until->diffForHumans()}."],
            ]);
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            if ($user) {
                $this->registerFailedAttempt($user);
            }
            $this->audit->log('auth.login_failed', $user, null, ['email' => $email], $user, $user?->tenant_id);
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

            return [
                'requires_2fa' => true,
                'challenge_token' => $challengeToken,
                'expires_in' => self::CHALLENGE_TTL_MINUTES * 60,
            ];
        }

        return $this->completeLogin($user, $deviceName, $ipAddress);
    }

    public function twoFactorChallenge(string $challengeToken, ?string $code, ?string $recoveryCode, ?string $deviceName = 'web', ?string $ipAddress = null): array
    {
        $userId = Cache::get("2fa_challenge:{$challengeToken}");
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

        $ok = $recoveryCode
            ? $this->consumeRecoveryCode($user, $recoveryCode)
            : $this->totp->verify($user->two_factor_secret, (string) $code);

        if (! $ok) {
            $this->registerFailedAttempt($user);
            $this->audit->log('auth.2fa_failed', $user, null, null, $user, $user->tenant_id);
            throw ValidationException::withMessages(['code' => ['Invalid code.']]);
        }

        Cache::forget("2fa_challenge:{$challengeToken}");

        return $this->completeLogin($user, $deviceName, $ipAddress);
    }

    public function changePassword(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($new)])->save();

        $accessToken = $user->currentAccessToken();
        if (isset($accessToken->id)) {
            $user->tokens()->whereKeyNot($accessToken->id)->delete();
        }

        $this->audit->log('auth.password_changed', $user, null, null, $user, $user->tenant_id);
    }

    public function forgotPassword(string $email): void
    {
        $this->context->runUnscoped(fn () => Password::sendResetLink(['email' => $email]));
    }

    public function resetPassword(array $data): void
    {
        $status = $this->context->runUnscoped(fn () => Password::reset(
            $data,
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
    }

    public function verifyEmailOtp(string $email, string $code): void
    {
        $user = User::withoutTenancy()->where('email', $email)->first();
        abort_if(! $user, 422, 'Invalid verification code.');

        if ($user->hasVerifiedEmail()) {
            return;
        }

        if (! $user->verifyEmailWithOtp($code)) {
            throw ValidationException::withMessages([
                'code' => ['Invalid or expired verification code.'],
            ]);
        }

        $this->audit->log('auth.email_verified', $user, null, null, $user, $user->tenant_id);
    }

    public function completeLogin(User $user, ?string $deviceName = 'web', ?string $ipAddress = null): array
    {
        $user->forceFill(['last_login_at' => now(), 'failed_login_attempts' => 0, 'locked_until' => null])->save();

        if ($user->is_super_admin ?? false) {
            $user->setRelation('roles', collect());
        } else {
            $user->load(['roles', 'business']);
        }

        $this->audit->log('auth.login', $user, null, null, $user, $user->tenant_id);

        return [
            'user' => $user,
            'token' => $this->issueToken($user, $deviceName, $ipAddress),
        ];
    }

    private function guardTenantStatus(User $user): void
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

    private function registerFailedAttempt(User $user): void
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

    private function consumeRecoveryCode(User $user, string $code): bool
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

    private function issueToken(User $user, ?string $deviceName = 'web', ?string $ipAddress = null): string
    {
        $abilities = (($user->is_super_admin ?? false) || $user->hasRole('owner'))
            ? ['*']
            : $user->permissionSlugs();

        $newToken = $user->createToken($deviceName ?? 'web', $abilities);

        if ($ipAddress) {
            $newToken->accessToken->forceFill(['ip_address' => $ipAddress])->save();
        }

        return $newToken->plainTextToken;
    }
}
