<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class StockTransferTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_full_transfer_lifecycle_moves_stock_between_warehouses(): void
    {
        $owner = $this->createTenant();
        $fromWarehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $toWarehouse = Warehouse::create(['business_id' => $owner->business_id, 'name' => 'Second Warehouse']);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $fromWarehouse->id, 20, 5);

        $transfer = $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $fromWarehouse->id,
            'to_warehouse_id' => $toWarehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 8]],
        ])->assertCreated()->json('data');

        $this->assertSame('pending', $transfer['status']);

        $this->postJson("/api/v1/stock-transfers/{$transfer['id']}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'in_transit');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(12, $product->fresh()->totalStock(), 0.001); // 20 - 8 dispatched

        $response = $this->postJson("/api/v1/stock-transfers/{$transfer['id']}/receive", [
            'items' => [['transfer_item_id' => $transfer['items'][0]['id'], 'quantity_received' => 8]],
        ]);
        $response->assertOk()->assertJsonPath('data.status', 'received');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(20, $product->fresh()->totalStock(), 0.001); // conserved overall
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'transfer_out', 'warehouse_id' => $fromWarehouse->id, 'quantity' => 8,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'transfer_in', 'warehouse_id' => $toWarehouse->id, 'quantity' => 8,
        ]);
    }

    public function test_transfer_requires_two_different_warehouses(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = Product::create(['name' => 'Widget', 'slug' => 'w-'.uniqid(), 'sku' => 'S-'.uniqid(), 'purchase_price' => 5, 'selling_price' => 10]);

        $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $warehouse->id,
            'to_warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422);
    }

    public function test_transfer_rejects_a_warehouse_belonging_to_another_tenant(): void
    {
        $ownerA = $this->createTenant('a@example.test');
        $warehouseA = $this->warehouseFor($ownerA);

        $ownerB = $this->createTenant('b@example.test');
        $warehouseB = $this->warehouseFor($ownerB);
        Sanctum::actingAs($ownerB);

        $product = Product::create(['name' => 'Widget', 'slug' => 'w-'.uniqid(), 'sku' => 'S-'.uniqid(), 'purchase_price' => 5, 'selling_price' => 10]);

        $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $warehouseA->id,
            'to_warehouse_id' => $warehouseB->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('from_warehouse_id');
    }

    public function test_only_pending_transfers_can_be_approved(): void
    {
        $owner = $this->createTenant();
        $fromWarehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $toWarehouse = Warehouse::create(['business_id' => $owner->business_id, 'name' => 'Second Warehouse']);
        $product = Product::create(['name' => 'Widget', 'slug' => 'w-'.uniqid(), 'sku' => 'S-'.uniqid(), 'purchase_price' => 5, 'selling_price' => 10]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $fromWarehouse->id, 5, 5);

        $transfer = $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $fromWarehouse->id, 'to_warehouse_id' => $toWarehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/stock-transfers/{$transfer['id']}/approve")->assertOk();
        $this->postJson("/api/v1/stock-transfers/{$transfer['id']}/approve")->assertStatus(422);
    }

    public function test_pending_transfer_can_be_cancelled_without_moving_stock(): void
    {
        $owner = $this->createTenant();
        $fromWarehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $toWarehouse = Warehouse::create(['business_id' => $owner->business_id, 'name' => 'Second Warehouse']);
        $product = Product::create(['name' => 'Widget', 'slug' => 'w-'.uniqid(), 'sku' => 'S-'.uniqid(), 'purchase_price' => 5, 'selling_price' => 10]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $fromWarehouse->id, 5, 5);

        $transfer = $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $fromWarehouse->id, 'to_warehouse_id' => $toWarehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/stock-transfers/{$transfer['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(5, $product->fresh()->totalStock(), 0.001);
    }

    public function test_cashier_cannot_request_a_stock_transfer(): void
    {
        $owner = $this->createTenant();
        $fromWarehouse = $this->warehouseFor($owner);
        $toWarehouse = Warehouse::create(['business_id' => $owner->business_id, 'name' => 'Second Warehouse']);
        $product = Product::create(['name' => 'Widget', 'slug' => 'w-'.uniqid(), 'sku' => 'S-'.uniqid(), 'purchase_price' => 5, 'selling_price' => 10]);

        $cashier = User::create([
            'tenant_id' => $owner->tenant_id, 'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->postJson('/api/v1/stock-transfers', [
            'from_warehouse_id' => $fromWarehouse->id, 'to_warehouse_id' => $toWarehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertForbidden();
    }
}
