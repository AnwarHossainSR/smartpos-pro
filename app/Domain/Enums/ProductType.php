<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum ProductType: string
{
    use HasValues;

    case Standard = 'standard';
    case Variant = 'variant';
    case Service = 'service';
    case Digital = 'digital';
    case Combo = 'combo';

    /** Services and digital goods have no on-hand quantity to decrement. */
    public function isStockable(): bool
    {
        return in_array($this, [self::Standard, self::Variant, self::Combo], true);
    }
}
