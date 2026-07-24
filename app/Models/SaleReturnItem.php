<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'sale_return_id', 'sale_item_id', 'product_id', 'variant_id',
        'quantity', 'unit_price', 'unit_cost', 'line_total',
    ];

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
