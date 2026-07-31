<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum CashMovementType: string
{
    use HasValues;

    case In = 'in';
    case Out = 'out';

    public function sign(): int
    {
        return $this === self::In ? 1 : -1;
    }
}
