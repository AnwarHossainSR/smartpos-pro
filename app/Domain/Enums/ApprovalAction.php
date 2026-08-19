<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

/**
 * The three till actions a supervisor PIN can authorise. Anything a cashier can
 * do that gives money away (or takes a completed sale back) belongs here.
 */
enum ApprovalAction: string
{
    use HasValues;

    case Discount = 'discount';
    case PriceOverride = 'price_override';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Discount => 'Discount over limit',
            self::PriceOverride => 'Price override',
            self::Void => 'Void sale',
        };
    }
}
