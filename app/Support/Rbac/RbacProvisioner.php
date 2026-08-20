<?php

namespace App\Support\Rbac;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Turns {@see PermissionCatalog} into rows.
 *
 * Permissions are global and synced once (idempotently); roles are created per tenant
 * with spatie's team id already bound, so `Role::create()` stamps the right `tenant_id`
 * and `assignRole('cashier')` resolves inside that tenant afterwards.
 *
 * Everything here is safe to re-run: registration calls it for a brand-new tenant, and
 * `PermissionSeeder` calls it after the catalogue grows.
 */
class RbacProvisioner
{
    public const GUARD = 'web';

    /**
     * Make the `permissions` table match the catalogue. Returns the number of rows
     * written, so a seeder can report something useful.
     */
    public function syncPermissions(): int
    {
        $catalogue = PermissionCatalog::all();

        /** @var array<string, Permission> $existing */
        $existing = Permission::query()
            ->where('guard_name', self::GUARD)
            ->get()
            ->keyBy('name')
            ->all();

        $now = now();
        $insert = [];
        $written = 0;

        foreach ($catalogue as $permission) {
            $current = $existing[$permission['slug']] ?? null;

            if (! $current) {
                $insert[] = [
                    'id' => (string) Str::uuid(),
                    'name' => $permission['slug'],
                    'guard_name' => self::GUARD,
                    'title' => $permission['title'],
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                continue;
            }

            // A catalogue entry whose label or grouping changed — rare, but the sync
            // is only meaningfully idempotent if it converges rather than drifts.
            $drift = $current->title !== $permission['title']
                || $current->module !== $permission['module']
                || $current->action !== $permission['action'];

            if ($drift) {
                $current->forceFill([
                    'title' => $permission['title'],
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                ])->save();

                $written++;
            }
        }

        if ($insert !== []) {
            // One bulk insert rather than ~110 round trips: this runs on every tenant
            // registration, and in tests on every `createTenant()`.
            Permission::query()->insert($insert);
            $written += count($insert);
        }

        if ($written > 0) {
            $this->forgetCachedPermissions();
        }

        return $written;
    }

    /**
     * Create (or top up) the system roles for one tenant: `owner` plus everything in
     * {@see PermissionCatalog::defaultRoles()}.
     *
     * The caller must already have the tenant bound in `TenantContext` — that is what
     * puts spatie's team id in place.
     */
    public function provisionDefaultRoles(string $tenantId): void
    {
        $this->syncPermissions();

        $allPermissionIds = Permission::query()->pluck('id')->all();

        $owner = $this->systemRole(
            $tenantId,
            'owner',
            'Owner',
            'Full access to everything within the business.',
        );
        $owner->permissions()->sync($allPermissionIds);

        foreach (PermissionCatalog::defaultRoles() as $slug => $definition) {
            $role = $this->systemRole($tenantId, $slug, $definition['name']);

            $permissionIds = $definition['permissions'] === '*'
                ? $allPermissionIds
                : Permission::query()->whereIn('name', $definition['permissions'])->pluck('id')->all();

            $role->permissions()->sync($permissionIds);
        }

        // `permissions()->sync()` writes the pivot directly, which fires no model
        // events — so spatie's own cache invalidation never runs. Flush by hand.
        $this->forgetCachedPermissions();
    }

    public function forgetCachedPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function systemRole(string $tenantId, string $slug, string $title, ?string $description = null): Role
    {
        return Role::query()->firstOrCreate(
            ['tenant_id' => $tenantId, 'name' => $slug, 'guard_name' => self::GUARD],
            ['title' => $title, 'description' => $description, 'is_system' => true],
        );
    }
}
