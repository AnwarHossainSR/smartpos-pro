<?php

namespace App\Models;

use App\Support\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\Permission\Models\Permission as SpatiePermission;

/**
 * Permissions are GLOBAL (not tenant-scoped): the catalogue is identical for every
 * business, and it is the tenant-scoped {@see Role} rows that decide who holds what.
 *
 * Naming follows the spatie convention: `name` carries the machine slug
 * (`products.create`) because that is the key the whole app already speaks — routes
 * (`permission:products.create`), `User::hasPermission()` and the frontend `can()`.
 * The human label lives in `title` ("Create Products").
 */
class Permission extends SpatiePermission
{
    use HasUuids;

    /** Pinned for the same reason as {@see Role::$guard_name}. */
    protected $guard_name = 'web';

    /**
     * Alias so older call sites (and API resources) can keep reading `->slug`.
     * Note this is an accessor, not a column: query builders must use `name`.
     */
    protected function slug(): Attribute
    {
        return Attribute::get(fn (): string => $this->name);
    }

    /**
     * Deliberately unscoped by tenant.
     *
     * `PermissionRegistrar` builds ONE process-wide permission cache from
     * `Permission::with('roles')`, so this relation must see every tenant's roles.
     * Constraining it here would either poison that shared cache with a single
     * tenant's rows or blow up on the fail-closed guard when no tenant is bound.
     * Cross-tenant leakage is prevented on the other side: a user's `roles`
     * relation is team-scoped, so only their own tenant's roles can ever match.
     */
    public function roles(): BelongsToMany
    {
        $relation = parent::roles();

        $relation->getQuery()->withoutGlobalScope(TenantScope::class);

        return $relation;
    }
}
