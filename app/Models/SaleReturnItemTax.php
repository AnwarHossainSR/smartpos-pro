<?php

namespace App\Models;

use App\Domain\Enums\TaxType;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tax reversed on a return. Derived from the original sale line's snapshotted
 * components, never recomputed from the current rate — a rate change between
 * sale and return must not change the refund.
 */
class SaleReturnItemTax extends Model
{
    use BelongsToTenant, HasShortUuids;

    protected $fillable = [
        'tenant_id', 'sale_return_item_id', 'tax_rate_id', 'name', 'rate',
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

    public function saleReturnItem(): BelongsTo
    {
        return $this->belongsTo(SaleReturnItem::class);
    }
}
