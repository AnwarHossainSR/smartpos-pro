<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

/**
 * Whether catalogue prices already contain tax. Retail in VAT/GST countries
 * quotes tax-inclusive shelf prices; B2B and US sales tax quote net prices and
 * add tax at the till.
 */
enum PriceDisplay: string
{
    use HasValues;

    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';

    public function isInclusive(): bool
    {
        return $this === self::Inclusive;
    }
}
