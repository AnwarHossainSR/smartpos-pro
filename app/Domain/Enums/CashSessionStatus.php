<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum CashSessionStatus: string
{
    use HasValues;

    case Open = 'open';
    case Closed = 'closed';
}
