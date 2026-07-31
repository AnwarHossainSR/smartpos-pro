<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

/**
 * At what granularity tax is rounded to whole minor units. The choice is not
 * cosmetic: rounding each line separately and rounding once per invoice can
 * differ by several minor units on a large basket, and different tax authorities
 * mandate different ones.
 */
enum TaxRoundingScope: string
{
    use HasValues;

    /** Round each line's tax (common in retail POS, required by NBR/BD). */
    case Line = 'line';

    /** Round each tax component's total across the invoice (India GST). */
    case Component = 'component';

    /** Round only the invoice's tax total (EU VAT permits this). */
    case Invoice = 'invoice';
}
