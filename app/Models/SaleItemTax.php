<?php

namespace App\Models;

use App\Domain\Enums\TaxType;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What one tax component actually charged on one sale line.
 *
 * Fully snapshotted (name, rate, type) rather than joined through to the live
 * `tax_rates` row: an invoice is a historical document, and renaming or
 * re-rating a tax next year must not rewrite what a customer was charged last
 * year. `tax_rate_id` is kept only so reports can group by component.
 */
class SaleItemTax extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'sale_item_id', 'tax_rate_id', 'name', 'rate',
        'type', 'is_compound', 'base_amount', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'type' => TaxType::class,
            'is_compound' => 'boolean',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }
}
