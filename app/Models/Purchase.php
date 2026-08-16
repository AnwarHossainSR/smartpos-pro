<?php

namespace App\Models;

use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\PurchaseStatus;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\SoftDeletes;

class Purchase extends Model
{
    use Auditable, BelongsToTenant, HasShortUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'business_id', 'warehouse_id', 'supplier_id', 'user_id',
        'reference_number', 'status', 'payment_status', 'subtotal', 'discount_amount',
        'tax_amount', 'shipping_amount', 'total', 'paid_amount', 'due_amount', 'note', 'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'purchased_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
