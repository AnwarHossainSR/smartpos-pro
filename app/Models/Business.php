<?php

namespace App\Models;

use App\Domain\Enums\BusinessType;
use App\Domain\Enums\PriceDisplay;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use App\Support\Concerns\HasShortUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use Auditable, BelongsToTenant, HasShortUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'type', 'legal_name', 'email', 'phone', 'tax_number',
        'tax_profile_id', 'price_display', 'currency', 'timezone', 'logo_path',
        'settings', 'onboarding_completed',
    ];

    protected function casts(): array
    {
        return [
            'type' => BusinessType::class,
            'price_display' => PriceDisplay::class,
            'settings' => 'array',
            'onboarding_completed' => 'boolean',
        ];
    }

    public function taxProfile(): BelongsTo
    {
        return $this->belongsTo(TaxProfile::class);
    }

    /** The tenant's tax rules, falling back to the generic profile when unset. */
    public function effectiveTaxProfile(): TaxProfile
    {
        return $this->taxProfile ?? TaxProfile::fallback();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }
}
