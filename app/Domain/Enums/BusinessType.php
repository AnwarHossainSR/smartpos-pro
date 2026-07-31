<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum BusinessType: string
{
    use HasValues;

    case Retail = 'retail';
    case Supermarket = 'supermarket';
    case Pharmacy = 'pharmacy';
    case Restaurant = 'restaurant';
    case Wholesale = 'wholesale';
}
