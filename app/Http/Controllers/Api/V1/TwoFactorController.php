<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogService;
use App\Services\Auth\TotpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TwoFactorController extends Controller
{
    public function __construct(protected TotpService $totp, protected AuditLogService $audit) {}

    /** Step 1: generate a pending (unconfirmed) secret and the QR provisioning URI. */
    public function enable(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_if($user->hasEnabledTwoFactor(), 422, 'Two-factor authentication is already enabled.');

        $secret = $this->totp->generateSecret();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => null])->save();

        return response()->json(['data' => [
            'secret' => $secret,
            'otpauth_url' => $this->totp->provisioningUri($secret, $user->email),
        ]]);
    }

    /** Step 2: confirm the pending secret with a code from the authenticator app. */
    public function confirm(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();

        abort_if(! $user->two_factor_secret || $user->hasEnabledTwoFactor(), 422, 'No pending two-factor setup.');

        if (! $this->totp->verify($user->two_factor_secret, $request->string('code'))) {
            throw ValidationException::withMessages(['code' => ['Invalid code.']]);
        }

        $codes = $this->totp->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes),
        ])->save();

        $this->audit->log('auth.2fa_enabled', $user, null, null, $user, $user->tenant_id);

        return response()->json(['data' => ['recovery_codes' => $codes]]);
    }

    /** Disables 2FA — requires the current password to confirm. */
    public function disable(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => ['Incorrect password.']]);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->audit->log('auth.2fa_disabled', $user, null, null, $user, $user->tenant_id);

        return response()->json(['message' => 'Two-factor authentication disabled.']);
    }

    /** Invalidates all existing recovery codes and issues a fresh set — requires the current password. */
    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        abort_unless($user->hasEnabledTwoFactor(), 422, 'Two-factor authentication is not enabled.');

        if (! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => ['Incorrect password.']]);
        }

        $codes = $this->totp->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $codes)])->save();

        $this->audit->log('auth.2fa_recovery_codes_regenerated', $user, null, null, $user, $user->tenant_id);

        return response()->json(['data' => ['recovery_codes' => $codes]]);
    }
}
