<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An ordered set of tax components applied together — what actually gets
 * attached to a product. "GST 18%" is one group of two components (CGST 9% +
 * SGST 9%), which is why the invoice can print the split.
 */
class TaxGroup extends Model
{
    use Auditable, BelongsToTenant, HasShortUuids;

    protected $fillable = ['tenant_id', 'name', 'code', 'is_default', 'is_active'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean'];
    }

    public function rates(): BelongsToMany
    {
        return $this->belongsToMany(TaxRate::class, 'tax_group_rate')
            ->withPivot('priority')
            ->orderByPivot('priority');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Active components in application order. Compound components must come
     * after the ones they compound on, so priority order is load-bearing, not
     * cosmetic.
     *
     * @return Collection<int, TaxRate>
     */
    public function orderedRates(): Collection
    {
        return $this->rates->where('is_active', true)->sortBy('pivot.priority')->values();
    }
}
