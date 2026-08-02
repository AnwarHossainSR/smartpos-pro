<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum StockDirection: string
{
    use HasValues;

    case In = 'in';
    case Out = 'out';

    /** +1 or -1 — the multiplier applied to a movement quantity when adjusting a balance. */
    public function sign(): int
    {
        return $this === self::In ? 1 : -1;
    }
}
