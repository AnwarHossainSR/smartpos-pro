<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Note: `delivery_zones` uses an auto-increment `id` (see the migration), unlike
 * most tables in this app which are UUID-keyed. The model must not declare
 * HasUuids or Eloquent will push a UUID string into an integer column.
 */
class DeliveryZone extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'delivery_fee', 'active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'delivery_fee' => 'decimal:2',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'zone_id');
    }
}
