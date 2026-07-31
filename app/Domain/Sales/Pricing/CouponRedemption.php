<?php

namespace App\Domain\Sales\Pricing;

use App\Models\Coupon;

final readonly class CouponRedemption
{
    public function __construct(
        public Coupon $coupon,
        public float $discount,
    ) {}
}
