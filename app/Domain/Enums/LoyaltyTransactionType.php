<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum LoyaltyTransactionType: string
{
    use HasValues;

    case Earn = 'earn';
    case Redeem = 'redeem';
    case Adjust = 'adjust';
}
