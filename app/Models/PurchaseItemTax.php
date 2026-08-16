<?php

namespace App\Models;

use App\Domain\Enums\TaxType;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Input tax on a purchase line — the reclaimable side of a VAT/GST return. */
class PurchaseItemTax extends Model
{
    use BelongsToTenant, HasShortUuids;

    protected $fillable = [
        'tenant_id', 'purchase_item_id', 'tax_rate_id', 'name', 'rate',
        'type', 'is_compound', 'base_amount', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'type' => TaxType::class,
            'is_compound' => 'boolean',
            'base_amount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }
}
