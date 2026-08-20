<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class RoleEscalationGuardTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function adminFor(User $owner): User
    {
        $admin = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Admin', 'email' => 'admin@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $admin->assignRole('admin');

        return $admin->fresh('roles');
    }

    public function test_a_user_cannot_change_their_own_roles(): void
    {
        $owner = $this->createTenant();
        $admin = $this->adminFor($owner);

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/users/{$admin->id}", [
            'roles' => ['cashier'],
        ])->assertForbidden();
    }

    public function test_non_owner_admin_cannot_grant_owner_role_to_another_user(): void
    {
        $owner = $this->createTenant();
        $admin = $this->adminFor($owner);
        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/users/{$cashier->id}", [
            'roles' => ['owner'],
        ])->assertForbidden();

        $this->assertFalse($cashier->fresh('roles')->hasRole('owner'));
    }

    public function test_owner_can_grant_owner_role_to_another_user(): void
    {
        $owner = $this->createTenant();
        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($owner);
        $this->putJson("/api/v1/users/{$cashier->id}", [
            'roles' => ['owner'],
        ])->assertOk();

        $this->assertTrue($cashier->fresh('roles')->hasRole('owner'));
    }

    public function test_non_owner_cannot_edit_the_owner_role_permissions(): void
    {
        $owner = $this->createTenant();
        $admin = $this->adminFor($owner);
        $ownerRole = Role::where('name', 'owner')->firstOrFail();

        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/roles/{$ownerRole->id}", [
            'name' => 'Owner', 'permissions' => ['pos.view'],
        ])->assertForbidden();
    }

    public function test_branch_scoped_manager_cannot_edit_a_user_in_another_branch(): void
    {
        $owner = $this->createTenant();

        $branchB = Branch::create([
            'tenant_id' => $owner->tenant_id,
            'business_id' => $owner->business_id,
            'name' => 'Branch B', 'code' => 'B',
        ]);

        $scopedRole = Role::create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'branch-admin-'.uniqid(), 'title' => 'Branch Admin',
        ]);
        $scopedRole->syncPermissions(['users.view', 'users.update']);

        $manager = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Branch Manager', 'email' => 'manager@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        // Pinned to one branch: `model_has_roles.branch_id` is what makes this grant
        // branch-scoped rather than tenant-wide.
        $manager->roles()->attach($scopedRole->id, [
            'tenant_id' => $owner->tenant_id,
            'branch_id' => $owner->branch_id,
        ]);

        $userInOtherBranch = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $branchB->id,
            'name' => 'Other Branch User', 'email' => 'otherbranch@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);

        Sanctum::actingAs($manager->fresh('roles'));
        $this->putJson("/api/v1/users/{$userInOtherBranch->id}", [
            'name' => 'Renamed',
        ])->assertForbidden();
    }
}
