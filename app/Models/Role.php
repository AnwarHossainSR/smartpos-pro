<?php

namespace App\Models;

use App\Support\Concerns\Auditable;
use App\Support\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Roles are tenant-scoped — that isolation is expressed twice, on purpose:
 * spatie's "teams" feature keys off `tenant_id` (so role lookups and role
 * assignments resolve within the current tenant), and {@see BelongsToTenant}
 * adds the global scope that makes a raw `Role::query()` — including route
 * model binding — fail closed rather than reach another business's rows.
 *
 * `name` holds the slug (`cashier`), `title` the human label ("Cashier"); see
 * {@see Permission} for why.
 */
class Role extends SpatieRole
{
    use Auditable, BelongsToTenant, HasUuids;

    /**
     * Pinned rather than derived. `auth:sanctum` calls `Auth::shouldUse('sanctum')`,
     * which rewrites `auth.defaults.guard` for the rest of the request — and spatie
     * falls back to that default for models no auth provider points at, so new roles
     * would silently be stamped `sanctum` (a guard with no provider) mid-request.
     * Everything here authenticates through the `web` provider; say so once.
     */
    protected $guard_name = 'web';

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * Alias so older call sites (and API resources) can keep reading `->slug`.
     * Note this is an accessor, not a column: query builders must use `name`.
     */
    protected function slug(): Attribute
    {
        return Attribute::get(fn (): string => $this->name);
    }
}
