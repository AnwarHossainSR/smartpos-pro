<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\StocktakeItem;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class StocktakeTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_create_and_start_stocktake(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $stocktake = $this->postJson('/api/v1/stocktakes', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Monthly Count',
        ])->assertCreated()->json('data');

        $this->assertSame('draft', $stocktake['status']);

        $this->postJson("/api/v1/stocktakes/{$stocktake['id']}/start")
            ->assertOk()
            ->assertJsonPath('data.status', 'counting');
    }

    public function test_record_counts_and_calculate_variance(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 10, 5);

        $stocktake = $this->postJson('/api/v1/stocktakes', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Test Count',
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/stocktakes/{$stocktake['id']}/start")->assertOk();

        $detail = $this->getJson("/api/v1/stocktakes/{$stocktake['id']}")->assertOk()->json('data');
        $itemId = $detail['items'][0]['id'];

        $this->postJson("/api/v1/stocktakes/{$stocktake['id']}/items/{$itemId}/count", [
            'counted_qty' => 12,
        ])->assertOk();

        app(TenantContext::class)->set($owner->tenant_id);
        $item = StocktakeItem::find($itemId);
        $this->assertEqualsWithDelta(2, $item->variance, 0.001);
    }

    public function test_complete_stocktake_posts_adjustments(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 10, 5);

        $stocktake = $this->postJson('/api/v1/stocktakes', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Adjust Count',
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/stocktakes/{$stocktake['id']}/start")->assertOk();

        $detail = $this->getJson("/api/v1/stocktakes/{$stocktake['id']}")->assertOk()->json('data');
        $itemId = $detail['items'][0]['id'];

        $this->postJson("/api/v1/stocktakes/{$stocktake['id']}/items/{$itemId}/count", [
            'counted_qty' => 15,
        ])->assertOk();

        $this->postJson("/api/v1/stocktakes/{$stocktake['id']}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertDatabaseHas('stock_movements', [
            'type' => 'adjustment_in',
            'warehouse_id' => $warehouse->id,
            'quantity' => 5,
            'reference_type' => 'App\\Models\\Stocktake',
            'reference_id' => $stocktake['id'],
        ]);
    }

    public function test_cancel_stocktake(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $stocktake = $this->postJson('/api/v1/stocktakes', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Cancel Count',
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/stocktakes/{$stocktake['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    public function test_cashier_cannot_create_stocktake(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);

        $cashier = User::create([
            'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier-'.uniqid().'@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));

        $this->postJson('/api/v1/stocktakes', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'name' => 'Count',
        ])->assertForbidden();
    }
}
