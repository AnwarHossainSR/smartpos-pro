<?php

namespace App\Models;

use App\Domain\Enums\ProductType;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'category_id', 'brand_id', 'unit_id', 'tax_group_id', 'default_supplier_id',
        'name', 'slug', 'sku', 'barcode', 'type', 'description', 'image_path',
        'purchase_price', 'selling_price', 'wholesale_price', 'tax_rate', 'tax_inclusive',
        'min_stock_level', 'max_stock_level', 'reorder_quantity', 'reorder_point',
        'reorder_lead_time_days', 'avg_daily_sales',
        'track_inventory', 'track_batches', 'track_serials', 'track_expiry', 'is_active', 'tags',
        'is_weighed', 'weight_unit', 'price_per_unit',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'purchase_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'wholesale_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'min_stock_level' => 'decimal:4',
            'max_stock_level' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'track_inventory' => 'boolean',
            'track_batches' => 'boolean',
            'track_serials' => 'boolean',
            'track_expiry' => 'boolean',
            'is_active' => 'boolean',
            'tags' => 'array',
            'reorder_point' => 'integer',
            'reorder_lead_time_days' => 'integer',
            'avg_daily_sales' => 'decimal:2',
            'is_weighed' => 'boolean',
            'price_per_unit' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function taxGroup(): BelongsTo
    {
        return $this->belongsTo(TaxGroup::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    public function inventoryBalances(): HasMany
    {
        return $this->hasMany(InventoryBalance::class);
    }

    /** Total on-hand quantity across every warehouse for the current tenant. */
    public function totalStock(): float
    {
        return (float) $this->inventoryBalances()->sum('quantity');
    }
}
