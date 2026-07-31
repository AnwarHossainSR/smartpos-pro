<?php

use App\Domain\Enums\PriceDisplay;
use App\Domain\Enums\RoundingMode;
use App\Domain\Enums\TaxRoundingScope;
use App\Domain\Enums\TaxSourcing;

return [

    /*
    |--------------------------------------------------------------------------
    | Fallback currency
    |--------------------------------------------------------------------------
    |
    | Money amounts belong to a tenant's business, which carries its own
    | `currency` column. This value is only the fallback used when an amount is
    | constructed outside any tenant context (console commands, unit tests).
    |
    */

    'currency' => env('POS_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Tax defaults
    |--------------------------------------------------------------------------
    |
    | Applied to a tenant that has not picked a country tax profile yet. Once a
    | profile is selected these are all overridden by the profile's own rules.
    |
    */

    'tax' => [
        'sourcing' => TaxSourcing::Origin->value,
        'rounding_scope' => TaxRoundingScope::Line->value,
        'rounding_mode' => RoundingMode::HalfUp->value,
        'price_display' => PriceDisplay::Exclusive->value,
    ],

];
