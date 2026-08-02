<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

enum RefundMethod: string
{
    use HasValues;

    case Cash = 'cash';
    case Card = 'card';
    case Bank = 'bank';
    case StoreCredit = 'store_credit';

    /** Store credit never leaves the till — it moves the customer's balance instead. */
    public function movesCash(): bool
    {
        return $this === self::Cash;
    }
}
