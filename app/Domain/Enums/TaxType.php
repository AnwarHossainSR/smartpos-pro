<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum TaxType: string
{
    use HasValues;

    /** `rate` is a percentage of the taxable base. */
    case Percentage = 'percentage';

    /** `rate` is a flat amount per unit sold (excise duty, bottle deposit, eco-fee). */
    case Fixed = 'fixed';
}
