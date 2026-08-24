<?php

namespace App\Models;

use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\SaleStatus;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Sale extends Model
{
    use Auditable, BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'business_id', 'branch_id', 'warehouse_id', 'customer_id', 'user_id',
        'coupon_id', 'invoice_number', 'public_token', 'status', 'payment_status', 'subtotal', 'discount_amount',
        'coupon_discount', 'tax_amount', 'shipping_amount', 'total', 'paid_amount', 'change_amount',
        'due_amount', 'note', 'sold_at', 'tender_currency', 'tender_amount', 'fx_rate',
    ];

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'payment_status' => PaymentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'coupon_discount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'shipping_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'due_amount' => 'decimal:2',
            'tender_amount' => 'decimal:2',
            'fx_rate' => 'decimal:6',
            'sold_at' => 'datetime',
        ];
    }

    /**
     * Every sale carries an unguessable token from birth: it is what the customer
     * order-status page and the Stripe pay link are addressed by, and a sale can
     * reach either surface long after checkout (a held cart resumed, a due settled
     * later), so minting it lazily would leave older rows unreachable.
     */
    protected static function booted(): void
    {
        static::creating(function (self $sale): void {
            $sale->public_token ??= Str::random(48);
        });
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }
}
