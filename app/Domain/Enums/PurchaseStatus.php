<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum PurchaseStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Ordered = 'ordered';
    case Approved = 'approved';
    case Partial = 'partial';
    case Received = 'received';
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function affectsStock(): bool
    {
        return in_array($this, [self::Partial, self::Received], true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::Ordered, self::Approved], true);
    }
}
