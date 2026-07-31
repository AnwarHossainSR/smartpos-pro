<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum PurchaseStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Ordered = 'ordered';
    case Partial = 'partial';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /** Goods only hit the ledger once they are actually on the shelf. */
    public function affectsStock(): bool
    {
        return in_array($this, [self::Partial, self::Received], true);
    }
}
