<?php

namespace App\Support\Concerns;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

trait HasRoles
{
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withPivot('branch_id');
    }

    public function assignRole(Role|string $role): void
    {
        $roleId = $role instanceof Role
            ? $role->id
            : Role::where('slug', $role)->value('id');

        if ($roleId) {
            $this->roles()->syncWithoutDetaching([$roleId]);
        }
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /** All permission slugs granted through any of the user's roles. */
    public function permissionSlugs(): array
    {
        $this->loadMissing('roles.permissions');

        return $this->roles
            ->flatMap(fn (Role $role) => $role->permissions)
            ->pluck('slug')
            ->unique()
            ->values()
            ->all();
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->is_super_admin ?? false) {
            return true;
        }

        // Business owners implicitly hold every permission within their tenant.
        if ($this->hasRole('owner')) {
            return true;
        }

        return in_array($slug, $this->permissionSlugs(), true);
    }
}
