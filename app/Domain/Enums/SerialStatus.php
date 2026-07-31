<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum SerialStatus: string
{
    use HasValues;

    case InStock = 'in_stock';
    case Sold = 'sold';
    case Returned = 'returned';
    case Damaged = 'damaged';

    /** Only these can be attached to a new sale line. */
    public function isSellable(): bool
    {
        return in_array($this, [self::InStock, self::Returned], true);
    }
}
