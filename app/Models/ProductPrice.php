<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'branch_id', 'customer_group', 'price',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }
}
