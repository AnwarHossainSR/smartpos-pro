<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum SaleStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Quotation = 'quotation';
    case Held = 'held';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /** Only a completed sale moves stock and books revenue. */
    public function affectsStock(): bool
    {
        return $this === self::Completed;
    }

    /** A held or draft cart can still be edited or abandoned without a correction document. */
    public function isEditable(): bool
    {
        return in_array($this, [self::Draft, self::Held, self::Quotation], true);
    }
}
