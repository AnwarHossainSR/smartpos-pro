<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Table extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    /** The migration creates `dining_tables`; `tables` is far too generic a name to claim. */
    protected $table = 'dining_tables';

    protected $fillable = [
        'tenant_id', 'branch_id', 'active_sale_id', 'name', 'area', 'seats', 'status', 'x', 'y',
    ];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'x' => 'decimal:2',
            'y' => 'decimal:2',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function activeSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'active_sale_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'table_id');
    }
}
