<?php

namespace App\Models;

use App\Domain\Enums\BusinessType;
use App\Domain\Enums\PriceDisplay;
use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Business extends Model
{
    use Auditable, BelongsToTenant, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'type', 'legal_name', 'email', 'phone', 'tax_number',
        'tax_profile_id', 'price_display', 'currency', 'timezone',
        'logo_path', 'logo_dark_path', 'favicon_path', 'receipt_logo_path', 'cover_path',
        'settings', 'onboarding_completed', 'onboarding_state', 'auto_post_journals',
    ];

    protected function casts(): array
    {
        return [
            'type' => BusinessType::class,
            'price_display' => PriceDisplay::class,
            'settings' => 'array',
            'onboarding_completed' => 'boolean',
            'auto_post_journals' => 'boolean',
            'onboarding_state' => 'array',
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
