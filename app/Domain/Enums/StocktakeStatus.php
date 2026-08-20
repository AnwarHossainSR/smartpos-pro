<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum StocktakeStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Counting = 'counting';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
