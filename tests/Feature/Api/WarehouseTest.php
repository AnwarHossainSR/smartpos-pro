<?php

namespace Tests\Feature\Api;

use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class WarehouseTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_create_and_update_a_warehouse(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/warehouses', [
            'name' => 'Overflow Store', 'code' => 'WH-2', 'branch_id' => $owner->branch_id,
        ]);
        $response->assertCreated()->assertJsonPath('data.name', 'Overflow Store');
        $id = $response->json('data.id');

        $this->putJson("/api/v1/warehouses/{$id}", ['name' => 'Overflow Store B', 'branch_id' => $owner->branch_id])
            ->assertOk()
            ->assertJsonPath('data.name', 'Overflow Store B');

        $this->getJson('/api/v1/warehouses')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_default_warehouse_cannot_be_deleted(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $default = $this->warehouseFor($owner);

        $this->deleteJson("/api/v1/warehouses/{$default->id}")->assertStatus(422);
    }

    public function test_warehouse_holding_stock_cannot_be_deleted(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $warehouse = Warehouse::create(['business_id' => $owner->business_id, 'name' => 'Stocked WH']);
        InventoryBalance::create(['product_id' => $this->makeProduct($owner)->id, 'warehouse_id' => $warehouse->id, 'quantity' => 5]);

        $this->deleteJson("/api/v1/warehouses/{$warehouse->id}")->assertStatus(422);
    }

    public function test_empty_non_default_warehouse_can_be_deleted(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $warehouse = Warehouse::create(['business_id' => $owner->business_id, 'name' => 'Empty WH']);

        $this->deleteJson("/api/v1/warehouses/{$warehouse->id}")->assertNoContent();
        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    }

    public function test_cashier_cannot_create_warehouses(): void
    {
        $owner = $this->createTenant();
        $cashier = User::create([
            'tenant_id' => $owner->tenant_id, 'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->postJson('/api/v1/warehouses', ['name' => 'Nope'])->assertForbidden();
    }

    protected function makeProduct(User $owner): Product
    {
        $sku = 'W-'.uniqid();

        return Product::create([
            'name' => 'Widget', 'slug' => Str::slug($sku), 'sku' => $sku,
            'purchase_price' => 1, 'selling_price' => 2,
        ]);
    }
}
