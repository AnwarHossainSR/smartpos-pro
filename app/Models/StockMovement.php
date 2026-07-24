<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Immutable stock-ledger entry. Never updated or deleted once written.
 */
class StockMovement extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null; // append-only: no updated_at

    protected $fillable = [
        'tenant_id', 'product_id', 'variant_id', 'warehouse_id', 'type', 'direction',
        'quantity', 'quantity_after', 'unit_cost', 'reference_type', 'reference_id',
        'user_id', 'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'quantity_after' => 'decimal:4',
            'unit_cost' => 'decimal:4',
        ];
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
