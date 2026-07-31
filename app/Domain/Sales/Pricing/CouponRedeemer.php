<?php

namespace App\Domain\Sales\Pricing;

use App\Models\Coupon;
use Illuminate\Validation\ValidationException;

/**
 * Re-validates and atomically consumes a coupon *inside* the checkout
 * transaction.
 *
 * Validating at the terminal and redeeming at checkout are different moments; a
 * coupon on its last redemption can be validated twice and spent twice. The row
 * lock here is what makes two concurrent checkouts against a `usage_limit: 1`
 * coupon resolve to exactly one redemption.
 */
class CouponRedeemer
{
    public function redeem(string $code, float $subtotal): CouponRedemption
    {
        $coupon = Coupon::where('code', strtoupper($code))->lockForUpdate()->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => ['Coupon not found.']]);
        }

        $result = $coupon->evaluate($subtotal);

        if (! $result['valid']) {
            throw ValidationException::withMessages(['coupon_code' => [$result['reason']]]);
        }

        $coupon->increment('used_count');

        return new CouponRedemption($coupon, (float) $result['discount']);
    }
}
