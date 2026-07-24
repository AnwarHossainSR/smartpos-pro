<?php

namespace App\Models;

use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'sale_id', 'method', 'amount', 'reference', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
