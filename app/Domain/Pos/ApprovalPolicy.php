<?php

namespace App\Domain\Pos;

use App\Domain\Enums\ApprovalAction;
use App\Models\Business;

/**
 * The tenant's till limits, read from `businesses.settings.approvals`.
 *
 * Defaults are deliberately permissive (100% discount allowed, overrides and
 * voids unsupervised) so switching the feature on is an explicit act by the
 * business rather than something that silently starts blocking cashiers.
 */
final readonly class ApprovalPolicy
{
    public const KEY = 'approvals';

    public function __construct(
        /** Percent of the basket's list value a cashier may discount unaided (0–100). */
        public float $maxDiscountPercent = 100.0,
        /** Optional absolute ceiling in the tenant's currency; null means no cap. */
        public ?float $maxDiscountAmount = null,
        public bool $requireApprovalForPriceOverride = false,
        public bool $requireApprovalForVoid = false,
        /** How long a granted approval stays usable. */
        public int $approvalTtlMinutes = 5,
    ) {}

    public static function fromBusiness(?Business $business): self
    {
        return self::fromArray((array) data_get($business?->settings, self::KEY, []));
    }

    /** @param  array<string, mixed>  $settings */
    public static function fromArray(array $settings): self
    {
        $amount = $settings['max_discount_amount'] ?? null;

        return new self(
            maxDiscountPercent: max(0.0, min(100.0, (float) ($settings['max_discount_percent'] ?? 100))),
            maxDiscountAmount: $amount === null || $amount === '' ? null : max(0.0, (float) $amount),
            requireApprovalForPriceOverride: (bool) ($settings['require_approval_for_price_override'] ?? false),
            requireApprovalForVoid: (bool) ($settings['require_approval_for_void'] ?? false),
            approvalTtlMinutes: max(1, min(60, (int) ($settings['approval_ttl_minutes'] ?? 5))),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'max_discount_percent' => $this->maxDiscountPercent,
            'max_discount_amount' => $this->maxDiscountAmount,
            'require_approval_for_price_override' => $this->requireApprovalForPriceOverride,
            'require_approval_for_void' => $this->requireApprovalForVoid,
            'approval_ttl_minutes' => $this->approvalTtlMinutes,
        ];
    }

    /**
     * Whether a discount of `$amount` on a basket whose list value is `$basketValue`
     * needs a supervisor. Compared with a cent of slack so a 10.00% discount does not
     * trip a 10% limit on a floating-point hair.
     */
    public function discountNeedsApproval(float $amount, float $basketValue): bool
    {
        if ($amount <= 0) {
            return false;
        }

        if ($this->maxDiscountAmount !== null && $amount > $this->maxDiscountAmount + 0.005) {
            return true;
        }

        $percent = $basketValue > 0 ? ($amount / $basketValue) * 100 : 100.0;

        return $percent > $this->maxDiscountPercent + 0.0001;
    }

    /** The ceiling that applies to a discount, expressed in currency, for the audit trail. */
    public function discountLimitFor(float $basketValue): float
    {
        $byPercent = round($basketValue * ($this->maxDiscountPercent / 100), 2);

        return $this->maxDiscountAmount === null
            ? $byPercent
            : min($byPercent, $this->maxDiscountAmount);
    }

    public function requiresApprovalFor(ApprovalAction $action): bool
    {
        return match ($action) {
            ApprovalAction::PriceOverride => $this->requireApprovalForPriceOverride,
            ApprovalAction::Void => $this->requireApprovalForVoid,
            ApprovalAction::Discount => $this->maxDiscountPercent < 100 || $this->maxDiscountAmount !== null,
        };
    }
}
