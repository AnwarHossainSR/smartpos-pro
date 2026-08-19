<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Inventory\StockLedgerService;
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
        $productId = $this->postJson('/api/v1/products', ['name' => 'Gadget', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertCreated()->json('data.id');

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
        $manager->assignRole('branch_manager');

        Sanctum::actingAs($owner);
        $productId = $this->postJson('/api/v1/products', ['name' => 'Gadget', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertCreated()->json('data.id');

        // A branch manager runs the floor: reads the catalogue and the day's numbers,
        // but does not edit or delete products.
        Sanctum::actingAs($manager->fresh('roles'));
        $this->getJson('/api/v1/products')->assertOk();
        $this->putJson("/api/v1/products/{$productId}", ['name' => 'Updated'])->assertForbidden();
        $this->deleteJson("/api/v1/products/{$productId}")->assertForbidden();
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

        $warehouse = $this->warehouseFor($owner);

        Sanctum::actingAs($owner);
        $productId = $this->postJson('/api/v1/products', ['name' => 'Gadget', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertCreated()->json('data.id');
        app(StockLedgerService::class)->addOpeningStock($productId, $warehouse->id, 5, 1);

        // The till is the cashier's whole job: they may sell, but not restock or
        // touch the catalogue.
        Sanctum::actingAs($cashier->fresh('roles'));
        $this->getJson('/api/v1/sales')->assertOk();
        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $productId, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 2]],
        ])->assertCreated();

        $this->postJson('/api/v1/products', ['name' => 'Nope', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertForbidden();
        $this->postJson('/api/v1/inventory/adjust', [
            'product_id' => $productId,
            'warehouse_id' => $warehouse->id,
            'direction' => 'in',
            'quantity' => 1,
        ])->assertForbidden();
    }
}
