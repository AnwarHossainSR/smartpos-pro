<?php

namespace App\Support\Concerns;

use App\Models\Tenant;
use App\Support\Scopes\TenantScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Applied to every tenant-owned model. It:
 *   1. registers the global TenantScope so reads are automatically constrained, and
 *   2. auto-fills tenant_id from the current TenantContext on insert.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model): void {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app(TenantContext::class)->id();
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Escape hatch: query across all tenants (super-admin / system jobs only). */
    public static function withoutTenancy(): Builder
    {
        return static::withoutGlobalScope(TenantScope::class);
    }
}
