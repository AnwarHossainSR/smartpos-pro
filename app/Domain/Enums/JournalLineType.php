<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum JournalLineType: string
{
    use HasValues;

    case Debit = 'debit';
    case Credit = 'credit';

    public function opposite(): self
    {
        return $this === self::Debit ? self::Credit : self::Debit;
    }
}
