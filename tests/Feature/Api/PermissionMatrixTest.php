<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_cashier_denied_admin_routes(): void
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
        $this->postJson('/api/v1/products', ['name' => 'Gadget', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertCreated();

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->getJson('/api/v1/products')->assertOk();
        $this->deleteJson("/api/v1/products/{$productId}")->assertForbidden();
    }

    public function test_manager_has_limited_admin_access(): void
    {
        $owner = $this->createTenant();

        $manager = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Manager', 'email' => 'manager@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $manager->assignRole('manager');

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/products', ['name' => 'Gadget', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertCreated();

        Sanctum::actingAs($manager->fresh('roles'));
        $this->getJson('/api/v1/products')->assertOk();
        $this->putJson('/api/v1/products/1', ['name' => 'Updated'])->assertOk();
        $this->deleteJson('/api/v1/products/1')->assertForbidden();
    }

    public function test_cashier_can_access_cashier_routes_only(): void
    {
        $owner = $this->createTenant();

        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier2@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/products', ['name' => 'Gadget', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertCreated();

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->getJson('/api/v1/sales')->assertOk();
        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $owner->branch_id,
            'items' => [['product_id' => 1, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertStatus(403);
    }
}
