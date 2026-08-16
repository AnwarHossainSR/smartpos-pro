<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturnItem extends Model
{
    use BelongsToTenant, HasShortUuids;

    protected $fillable = [
        'tenant_id', 'sale_return_id', 'sale_item_id', 'product_id', 'variant_id',
        'quantity', 'unit_price', 'unit_cost', 'line_total',
    ];

    /** The reversed per-component tax, derived from the original sale line. */
    public function taxes(): HasMany
    {
        return $this->hasMany(SaleReturnItemTax::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:4',
            'line_total' => 'decimal:2',
        ];
    }
}
