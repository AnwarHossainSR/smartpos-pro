<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum TransferStatus: string
{
    use HasValues;

    case Pending = 'pending';
    case Approved = 'approved';
    case InTransit = 'in_transit';
    case Received = 'received';
    case Cancelled = 'cancelled';

    /** A transfer can only be cancelled while the goods have not yet landed. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Approved, self::InTransit], true);
    }
}
