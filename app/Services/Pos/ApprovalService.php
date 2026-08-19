<?php

namespace App\Services\Pos;

use App\Domain\Enums\ApprovalAction;
use App\Domain\Pos\ApprovalPolicy;
use App\Exceptions\ApprovalRequiredException;
use App\Models\Business;
use App\Models\PosApproval;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Supervisor approvals at the till.
 *
 * The whole point of this service is that the gate is *server-side*: the terminal
 * may open a PIN dialog, but the checkout endpoint independently re-derives the
 * limit and refuses the sale unless a matching, unconsumed, unexpired approval
 * accompanies it. A cashier calling the API directly gains nothing.
 *
 * The PIN itself is a 4–8 digit secret, which is trivially brute-forceable — so
 * verification is rate limited per requesting cashier and locks out for a quarter
 * of an hour after a handful of misses, and every attempt (granted or denied) is
 * written to the audit trail.
 */
class ApprovalService
{
    /** Misses before the cashier's terminal is locked out of the PIN prompt. */
    public const MAX_ATTEMPTS = 5;

    public const LOCKOUT_SECONDS = 900;

    /** Slack (in currency units) when matching a request against what was approved. */
    private const EPSILON = 0.005;

    public function __construct(protected AuditLogService $audit) {}

    public function policyFor(?Business $business): ApprovalPolicy
    {
        return ApprovalPolicy::fromBusiness($business);
    }

    /**
     * Verify a PIN and mint a single-use approval.
     *
     * @return array{approval: PosApproval, token: string}
     *
     * @throws ValidationException on a wrong PIN or while locked out
     */
    public function grant(
        User $requester,
        ApprovalAction $action,
        #[\SensitiveParameter] string $pin,
        float $requestedValue = 0.0,
        ?float $limitValue = null,
        ?string $saleId = null,
        ?string $reason = null,
    ): array {
        $key = $this->throttleKey($requester);

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            $this->audit->log('pos.approval_locked', null, null, [
                'action' => $action->value,
                'requested_by' => $requester->id,
                'available_in_seconds' => $seconds,
            ], $requester, $requester->tenant_id);

            throw ValidationException::withMessages([
                'pin' => ["Too many incorrect PINs. Try again in {$seconds} seconds."],
            ]);
        }

        $approver = $this->resolveApprover($pin);

        if (! $approver) {
            RateLimiter::hit($key, self::LOCKOUT_SECONDS);

            $this->audit->log('pos.approval_denied', null, null, [
                'action' => $action->value,
                'requested_by' => $requester->id,
                'requested_value' => round($requestedValue, 2),
                'limit_value' => $limitValue === null ? null : round($limitValue, 2),
                'reason' => 'invalid_pin',
            ], $requester, $requester->tenant_id);

            throw ValidationException::withMessages([
                'pin' => ['That PIN is not recognised, or the holder may not approve this action.'],
            ]);
        }

        RateLimiter::clear($key);

        $policy = $this->policyFor($requester->business ?? Business::find($requester->business_id));
        $token = Str::random(48);

        $approval = PosApproval::create([
            'business_id' => $requester->business_id,
            'action' => $action,
            'approver_id' => $approver->id,
            'requested_by_id' => $requester->id,
            'sale_id' => $saleId,
            'requested_value' => round($requestedValue, 2),
            'limit_value' => $limitValue === null ? null : round($limitValue, 2),
            'reason' => $reason,
            'token_hash' => $this->hashToken($token),
            'expires_at' => now()->addMinutes($policy->approvalTtlMinutes),
        ]);

        // Who approved what, for whom, over which limit — the audit row this
        // whole feature exists to produce.
        $this->audit->log('pos.approval_granted', $approval, null, [
            'action' => $action->value,
            'approved_by' => $approver->id,
            'approved_by_name' => $approver->name,
            'requested_by' => $requester->id,
            'requested_by_name' => $requester->name,
            'requested_value' => round($requestedValue, 2),
            'limit_value' => $limitValue === null ? null : round($limitValue, 2),
            'sale_id' => $saleId,
            'reason' => $reason,
        ], $approver, $requester->tenant_id);

        return ['approval' => $approval, 'token' => $token];
    }

    /**
     * Gate one till action. A caller who may approve for themselves passes
     * straight through; anyone else must present a token that was minted for
     * them, for this action, and covers at least the value being requested.
     *
     * Call this inside the sale's transaction — the approval is consumed under a
     * row lock so a replay racing the original loses.
     *
     * @throws ApprovalRequiredException
     */
    public function enforce(
        User $requester,
        ApprovalAction $action,
        ?string $token,
        float $requestedValue = 0.0,
        ?float $limitValue = null,
        ?string $saleId = null,
    ): ?PosApproval {
        $requester->loadMissing('roles.permissions');

        if ($requester->hasPermission('pos.approve')) {
            return null;
        }

        if (! $token) {
            throw new ApprovalRequiredException($action, $requestedValue, $limitValue);
        }

        /** @var PosApproval|null $approval */
        $approval = PosApproval::where('token_hash', $this->hashToken($token))
            ->lockForUpdate()
            ->first();

        if (
            ! $approval
            || ! $approval->isUsable()
            || $approval->action !== $action
            || $approval->requested_by_id !== $requester->id
            // The supervisor authorised a specific number; the cashier cannot then
            // apply a larger one under the same approval.
            || (float) $approval->requested_value + self::EPSILON < $requestedValue
        ) {
            throw new ApprovalRequiredException(
                $action,
                $requestedValue,
                $limitValue,
                'This approval is not valid for this action — ask a manager to approve again.',
            );
        }

        $approval->forceFill([
            'consumed_at' => now(),
            'sale_id' => $saleId ?? $approval->sale_id,
        ])->save();

        return $approval;
    }

    /** Sets (or replaces) a user's own approval PIN. */
    public function setPin(User $user, #[\SensitiveParameter] string $pin, ?User $actor = null): void
    {
        $user->forceFill([
            'approval_pin' => Hash::make($pin),
            'approval_pin_set_at' => now(),
        ])->save();

        $this->audit->log('user.approval_pin_set', $user, null, null, $actor ?? $user, $user->tenant_id);
    }

    /** Removes a PIN — the user can no longer authorise anything at the till. */
    public function clearPin(User $user, ?User $actor = null): void
    {
        $user->forceFill(['approval_pin' => null, 'approval_pin_set_at' => null])->save();

        $this->audit->log('user.approval_pin_cleared', $user, null, null, $actor, $user->tenant_id);
    }

    /**
     * The one user in this tenant whose PIN matches *and* who is allowed to approve.
     * Tenant isolation comes from the global scope: a PIN from another business
     * simply is not in the candidate set.
     */
    protected function resolveApprover(#[\SensitiveParameter] string $pin): ?User
    {
        $candidates = User::query()
            ->whereNotNull('approval_pin')
            ->where('status', 'active')
            ->with('roles.permissions')
            ->get();

        foreach ($candidates as $candidate) {
            if (Hash::check($pin, (string) $candidate->approval_pin) && $candidate->hasPermission('pos.approve')) {
                return $candidate;
            }
        }

        return null;
    }

    protected function hashToken(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    protected function throttleKey(User $requester): string
    {
        return 'pos-approval-pin:'.$requester->tenant_id.':'.$requester->id;
    }
}
