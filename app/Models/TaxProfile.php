<?php

namespace App\Models;

use App\Domain\Enums\PriceDisplay;
use App\Domain\Enums\RoundingMode;
use App\Domain\Enums\TaxRoundingScope;
use App\Domain\Enums\TaxSourcing;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A country's tax rules: how tax is sourced, rounded and displayed, and what a
 * compliant invoice must carry.
 *
 * Deliberately not tenant-scoped — these are facts about a jurisdiction, the
 * same for every tenant trading there. A tenant selects one; it does not own it.
 */
class TaxProfile extends Model
{
    use HasUuids;

    protected $fillable = [
        'code', 'name', 'country_code', 'sourcing', 'rounding_scope', 'rounding_mode',
        'price_display', 'tax_number_label', 'tax_number_pattern', 'required_invoice_fields', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sourcing' => TaxSourcing::class,
            'rounding_scope' => TaxRoundingScope::class,
            'rounding_mode' => RoundingMode::class,
            'price_display' => PriceDisplay::class,
            'required_invoice_fields' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /** The profile used when a tenant has not chosen one. */
    public static function fallback(): self
    {
        return new self([
            'code' => 'GENERIC',
            'name' => 'Generic',
            'sourcing' => config('pos.tax.sourcing'),
            'rounding_scope' => config('pos.tax.rounding_scope'),
            'rounding_mode' => config('pos.tax.rounding_mode'),
            'price_display' => config('pos.tax.price_display'),
            'tax_number_label' => 'Tax number',
            'required_invoice_fields' => [],
        ]);
    }
}
