<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum QuotationStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Converted = 'converted';

    /** A converted quotation is frozen — it now has a sale behind it. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Sent], true);
    }
}
