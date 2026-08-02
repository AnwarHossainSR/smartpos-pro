<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum DiscountType: string
{
    use HasValues;

    case Percentage = 'percentage';
    case Fixed = 'fixed';

    /** Resolves the money value of this discount against a base amount. */
    public function apply(float $value, float $base): float
    {
        return round($this === self::Percentage ? $base * ($value / 100) : $value, 2);
    }
}
