<?php

namespace App\Models;

use App\Domain\Enums\QuotationStatus;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use BelongsToTenant, HasShortUuids;
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'user_id', 'converted_to_sale_id',
        'reference_number', 'status', 'subtotal', 'discount_amount', 'tax_amount',
        'total', 'note', 'terms', 'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'subtotal' => 'decimal:4',
            'discount_amount' => 'decimal:4',
            'tax_amount' => 'decimal:4',
            'total' => 'decimal:4',
            'valid_until' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function convertedToSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'converted_to_sale_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }
}
