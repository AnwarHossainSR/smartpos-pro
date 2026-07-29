<?php

namespace App\Support;

class Money
{
    /** Locale-aware currency formatting for server-rendered documents (invoices, slips). */
    public static function format(float $amount, string $currency): string
    {
        $formatter = new \NumberFormatter('en', \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency($amount, $currency);
    }
}
