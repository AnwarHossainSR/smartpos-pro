<?php

namespace App\Domain\Enums;

use App\Domain\Enums\Concerns\HasValues;

/**
 * Which party's address decides the applicable tax zone. Over-the-counter retail
 * is origin-sourced (the shop's rate applies); delivered goods and most B2B
 * cross-border supply is destination-sourced (the customer's rate applies).
 */
enum TaxSourcing: string
{
    use HasValues;

    case Origin = 'origin';
    case Destination = 'destination';
}
