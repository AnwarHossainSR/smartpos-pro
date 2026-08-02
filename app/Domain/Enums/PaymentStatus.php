<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum PaymentStatus: string
{
    use HasValues;

    case Paid = 'paid';
    case Partial = 'partial';
    case Due = 'due';

    /** Derives the settlement state from what is still outstanding on a document. */
    public static function fromAmounts(float $paid, float $total): self
    {
        if (round($total - $paid, 2) <= 0) {
            return self::Paid;
        }

        return $paid > 0 ? self::Partial : self::Due;
    }
}
