<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class StockMovementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_list_stock_movements_for_a_product(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 20, 5);

        $this->postJson('/api/v1/inventory/adjust', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'direction' => 'out', 'quantity' => 3,
        ])->assertCreated();

        $response = $this->getJson("/api/v1/inventory/movements?product_id={$product->id}");

        $response->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame('adjustment_out', $response->json('data.0.type'));
        $this->assertSame('opening', $response->json('data.1.type'));
        $this->assertEquals(17, $response->json('data.0.quantity_after'));
    }

    public function test_movements_can_be_filtered_by_warehouse_and_direction(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 20, 5);

        $this->getJson('/api/v1/inventory/movements?direction=out')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/inventory/movements?direction=in')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/inventory/movements?warehouse_id=999999')->assertOk()->assertJsonCount(0, 'data');
    }
}
