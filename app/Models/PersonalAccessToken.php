<?php

namespace App\Models;

use App\Support\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

/**
 * Sanctum resolves the token owner (tokenable) before any route middleware has
 * bound a tenant into TenantContext, so the default relation would trip
 * TenantScope's fail-closed guard on every authenticated request. This is a
 * framework-internal lookup by primary key, not a listing — safe to bypass
 * tenant scoping here specifically.
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    public function tokenable(): MorphTo
    {
        return parent::tokenable()->withoutGlobalScope(TenantScope::class);
    }
}
