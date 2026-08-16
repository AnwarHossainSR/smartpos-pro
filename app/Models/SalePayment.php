<?php

namespace App\Models;

use App\Domain\Enums\PaymentMethod;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalePayment extends Model
{
    use BelongsToTenant, HasUuids;

    protected $fillable = ['tenant_id', 'sale_id', 'method', 'amount', 'reference', 'paid_at'];

    protected function casts(): array
    {
        return ['method' => PaymentMethod::class, 'amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
