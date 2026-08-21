<?php

namespace Database\Seeders;

use App\Support\Rbac\PermissionCatalog;
use App\Support\Rbac\RbacProvisioner;

/**
 * Brings the global `permissions` table in line with the catalogue.
 *
 * Idempotent, and independent of tenants: run it after adding a module to
 * {@see PermissionCatalog} so existing installs pick up the new
 * slugs without re-registering anyone. Tenant roles are provisioned separately, at
 * registration (see TenantProvisioner).
 */
class PermissionSeeder extends TransactionalSeeder
{
    protected function seed(): void
    {
        $written = app(RbacProvisioner::class)->syncPermissions();

        $this->command?->info("Permissions synced ({$written} written).");
    }
}
