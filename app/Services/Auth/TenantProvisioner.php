<?php

namespace App\Services\Auth;

use App\Models\Branch;
use App\Models\Business;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Rbac\PermissionCatalog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bootstraps a brand-new tenant on registration: the tenant record, its first
 * business, a default branch + warehouse, the default role set, and the owner
 * user — all in one transaction.
 */
class TenantProvisioner
{
    public function __construct(protected TenantContext $context) {}

    /**
     * @param  array{name:string, email:string, password:string, business_name:string, business_type?:string, currency?:string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $tenant = Tenant::create([
                'name' => $data['business_name'],
                'slug' => $this->uniqueSlug($data['business_name']),
                'plan' => 'free',
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
            ]);

            // Bind context so all subsequent tenant-scoped creates are isolated.
            $this->context->set($tenant->id);

            $business = Business::create([
                'tenant_id' => $tenant->id,
                'name' => $data['business_name'],
                'type' => $data['business_type'] ?? 'retail',
                'email' => $data['email'],
                'currency' => $data['currency'] ?? 'USD',
            ]);

            $branch = Branch::create([
                'business_id' => $business->id,
                'name' => 'Main Branch',
                'code' => 'MAIN',
                'is_default' => true,
            ]);

            Warehouse::create([
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'name' => 'Main Warehouse',
                'code' => 'WH-MAIN',
                'is_default' => true,
            ]);

            $this->provisionRoles($tenant);

            $owner = User::create([
                'tenant_id' => $tenant->id,
                'business_id' => $business->id,
                'branch_id' => $branch->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => 'active',
            ]);

            $owner->assignRole('owner');

            return $owner->load('roles', 'business', 'branch');
        });
    }

    /** Ensure the global permission rows exist, then create this tenant's roles. */
    public function provisionRoles(Tenant $tenant): void
    {
        foreach (PermissionCatalog::all() as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        $allPermissionIds = Permission::pluck('id')->all();

        $owner = Role::create([
            'tenant_id' => $tenant->id,
            'name' => 'Owner',
            'slug' => 'owner',
            'description' => 'Full access to everything within the business.',
            'is_system' => true,
        ]);
        $owner->permissions()->sync($allPermissionIds);

        foreach (PermissionCatalog::defaultRoles() as $slug => $definition) {
            $role = Role::create([
                'tenant_id' => $tenant->id,
                'name' => $definition['name'],
                'slug' => $slug,
                'is_system' => true,
            ]);

            $permissionIds = $definition['permissions'] === '*'
                ? $allPermissionIds
                : Permission::whereIn('slug', $definition['permissions'])->pluck('id')->all();

            $role->permissions()->sync($permissionIds);
        }
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'tenant';
        $slug = $base;
        $i = 1;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
