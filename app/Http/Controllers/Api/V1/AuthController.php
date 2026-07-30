<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Resources\UserResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\TenantProvisioner;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(protected TenantProvisioner $provisioner, protected AuditLogService $audit) {}

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
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            $this->audit->log('auth.login_failed', $user, null, ['email' => $request->email], $user, $user?->tenant_id);

            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->status === 'suspended') {
            throw ValidationException::withMessages([
                'email' => ['This account is suspended.'],
            ]);
        }

        if (! ($user->is_super_admin ?? false) && $user->tenant_id) {
            $tenant = Tenant::find($user->tenant_id);

            if (! $tenant || ! in_array($tenant->status, ['trialing', 'active'], true)) {
                abort(response()->json([
                    'message' => 'This account has been suspended.',
                    'code' => 'tenant_suspended',
                ], 403));
            }
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $user->load('roles');

        $this->audit->log('auth.login', $user, null, null, $user, $user->tenant_id);

        $token = $this->issueToken($user, $request);

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /** Always responds the same way regardless of whether the email exists, to avoid leaking account existence. */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json(['message' => 'If that email is registered, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                $user->tokens()->delete();

                event(new PasswordReset($user));

                $this->audit->log('auth.password_reset', $user, null, null, $user, $user->tenant_id);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json(['message' => 'Password reset. All existing sessions have been signed out.']);
    }

    /** Public, signed link — clicked directly from the verification email, no bearer token available. */
    public function verifyEmail(Request $request, int $id, string $hash): RedirectResponse
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired verification link.');

        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403, 'Invalid verification link.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->audit->log('auth.email_verified', $user, null, null, $user, $user->tenant_id);
        }

        return redirect(rtrim((string) config('app.frontend_url'), '/').'/login?verified=1');
    }

    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification email sent.']);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles', 'business', 'branch'));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
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
    public function revokeSession(Request $request, int $tokenId): JsonResponse
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
