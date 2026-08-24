<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\TwoFactorChallengeRequest;
use App\Http\Requests\Auth\UpdatePreferencesRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TenantProvisioner;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(
        private TenantProvisioner $provisioner,
        private AuthService $auth,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->provisioner->register($request->validated());
        $result = $this->auth->completeLogin($user, $request->input('device_name', 'web'));

        return $this->created([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->auth->login(
            $request->email,
            $request->password,
            $request->input('device_name', 'web')
        );

        if (isset($result['requires_2fa'])) {
            return $this->success($result);
        }

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    public function twoFactorChallenge(TwoFactorChallengeRequest $request): JsonResponse
    {
        $result = $this->auth->twoFactorChallenge(
            $request->challenge_token,
            $request->input('code'),
            $request->input('recovery_code'),
            $request->input('device_name', 'web')
        );

        return $this->success([
            'user' => new UserResource($result['user']),
            'token' => $result['token'],
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->auth->forgotPassword($request->email);

        return $this->success(null, 'If that email is registered, a reset link has been sent.');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $this->auth->resetPassword($request->only('email', 'password', 'password_confirmation', 'token'));

        return $this->success(null, 'Password reset. All existing sessions have been signed out.');
    }

    public function verifyEmail(Request $request, string $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired verification link.');
        $user = User::withoutTenancy()->findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'Invalid verification link.');
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }

        return redirect(rtrim((string) config('app.frontend_url'), '/').'/login?verified=1');
    }

    public function verifyEmailOtp(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);
        $this->auth->verifyEmailOtp($request->email, $request->code);

        return $this->success(null, 'Email verified successfully.');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return $this->success(null, 'Email already verified.');
        }
        $user->sendEmailVerificationNotification();

        return $this->success(null, 'Verification code sent.');
    }

    public function me(Request $request): UserResource
    {
        $user = $request->user();
        if ($user->is_super_admin ?? false) {
            $user->setRelation('roles', collect());
            $user->load('business');
        } else {
            $user->load('roles', 'business', 'branch');
        }

        return new UserResource($user);
    }

    public function updatePreferences(UpdatePreferencesRequest $request): UserResource
    {
        $user = $request->user();
        $user->fill(['theme' => $request->validated('theme')])->saveQuietly();
        if ($user->is_super_admin ?? false) {
            $user->setRelation('roles', collect());
            $user->load('business');
        } else {
            $user->load('roles', 'business', 'branch');
        }

        return new UserResource($user);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return $this->success(null, 'Logged out.');
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $this->auth->changePassword($request->user(), $request->string('current_password'), $request->string('new_password'));

        return $this->success(null, 'Password updated. Other sessions have been signed out.');
    }

    public function twoFactorStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'enabled' => $user->hasEnabledTwoFactor(),
            'recovery_codes_remaining' => $user->hasEnabledTwoFactor()
                ? count($user->two_factor_recovery_codes ?? [])
                : 0,
        ]);
    }

    public function lockoutStatus(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->success([
            'is_locked' => $user->isLocked(),
            'locked_until' => $user->locked_until?->toIso8601String(),
            'failed_attempts' => $user->failed_login_attempts ?? 0,
        ]);
    }

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

        return $this->success($sessions);
    }

    public function revokeSession(Request $request, string $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($tokenId)->delete();
        abort_if($deleted === 0, 404, 'Session not found.');

        return $this->success(null, 'Session revoked.');
    }

    public function revokeAllSessions(Request $request): JsonResponse
    {
        $current = $request->user()->currentAccessToken();
        $request->user()->tokens()
            ->when($current, fn ($q) => $q->whereKeyNot($current->id))
            ->delete();

        return $this->success(null, 'All other sessions revoked.');
    }
}
