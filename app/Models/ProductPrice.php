<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;

class ProductPrice extends Model
{
    use BelongsToTenant, HasShortUuids;

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'branch_id', 'customer_group', 'price',
    ];

    protected function casts(): array
    {
        return ['price' => 'decimal:2'];
    }
}
