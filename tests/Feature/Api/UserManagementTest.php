<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_invite_a_user_and_assign_a_role(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'New Cashier',
            'email' => 'newcashier@example.test',
            'roles' => ['cashier'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'invited')
            ->assertJsonPath('data.roles', ['cashier']);

        $this->assertDatabaseHas('users', ['email' => 'newcashier@example.test', 'tenant_id' => $owner->tenant_id]);
    }

    public function test_cashier_cannot_create_users(): void
    {
        $owner = $this->createTenant();
        $cashier = User::create([
            'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->postJson('/api/v1/users', ['name' => 'X', 'email' => 'x@example.test'])->assertForbidden();
    }

    public function test_owner_account_cannot_be_deleted(): void
    {
        $owner = $this->createTenant();
        $admin = User::create([
            'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'password123', 'status' => 'active',
        ]);
        $admin->assignRole('admin');

        Sanctum::actingAs($admin->fresh('roles'));
        $this->deleteJson("/api/v1/users/{$owner->id}")->assertStatus(422);
    }

    public function test_roles_endpoint_lists_permissions(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'owner'])
            ->assertJsonFragment(['slug' => 'cashier'])
            ->assertJsonStructure(['data' => [['id', 'name', 'slug', 'permissions']]]);
    }
}
