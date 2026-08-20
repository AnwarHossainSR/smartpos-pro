<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Supplier;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ReorderSuggestionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_returns_products_below_reorder_point(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $supplier = Supplier::create(['name' => 'Test Supplier']);

        Product::create([
            'name' => 'Low Stock Item', 'slug' => 'low-stock-'.uniqid(),
            'sku' => 'SKU-LOW-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
            'reorder_point' => 20, 'reorder_lead_time_days' => 7,
            'avg_daily_sales' => 2, 'default_supplier_id' => $supplier->id,
            'track_inventory' => true,
        ]);

        Product::create([
            'name' => 'High Stock Item', 'slug' => 'high-stock-'.uniqid(),
            'sku' => 'SKU-HIGH-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
            'reorder_point' => 5, 'reorder_lead_time_days' => 7,
            'avg_daily_sales' => 1,
            'track_inventory' => true,
        ]);

        app(StockLedgerService::class)->addOpeningStock(
            Product::where('name', 'Low Stock Item')->first()->id,
            $warehouse->id,
            5
        );

        app(StockLedgerService::class)->addOpeningStock(
            Product::where('name', 'High Stock Item')->first()->id,
            $warehouse->id,
            50
        );

        $response = $this->getJson('/api/v1/reorder-suggestions');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Low Stock Item', $data[0]['product']['name']);
    }

    public function test_suggested_qty_is_calculated_correctly(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $supplier = Supplier::create(['name' => 'Test Supplier']);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-W-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
            'reorder_point' => 10, 'reorder_lead_time_days' => 7,
            'avg_daily_sales' => 3, 'default_supplier_id' => $supplier->id,
            'track_inventory' => true,
        ]);

        app(StockLedgerService::class)->addOpeningStock(
            $product->id, $warehouse->id, 5
        );

        $response = $this->getJson('/api/v1/reorder-suggestions');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);

        // suggested_qty = (3 * 7) - 5 + 10 = 26
        $this->assertEquals(26, $data[0]['suggested_qty']);
    }

    public function test_creates_draft_purchase_order_from_suggestions(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $supplier = Supplier::create(['name' => 'Acme Supply']);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-W-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
            'reorder_point' => 10, 'reorder_lead_time_days' => 7,
            'avg_daily_sales' => 3, 'default_supplier_id' => $supplier->id,
            'track_inventory' => true,
        ]);

        $response = $this->postJson('/api/v1/reorder-suggestions/create-draft-po', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 26,
                    'unit_cost' => 5,
                    'supplier_id' => $supplier->id,
                ],
            ],
        ]);

        $response->assertCreated();
        $purchases = $response->json('data');
        $this->assertCount(1, $purchases);
        $this->assertEquals('draft', $purchases[0]['status']);
        $this->assertEquals($supplier->id, $purchases[0]['supplier']['id']);
        $this->assertEquals(26, $purchases[0]['items'][0]['quantity']);

        $this->assertDatabaseHas('purchases', [
            'supplier_id' => $supplier->id,
            'status' => 'draft',
        ]);
    }

    public function test_drafts_are_grouped_by_supplier(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $supplier1 = Supplier::create(['name' => 'Supplier One']);
        $supplier2 = Supplier::create(['name' => 'Supplier Two']);

        $product1 = Product::create([
            'name' => 'Widget A', 'slug' => 'widget-a-'.uniqid(),
            'sku' => 'SKU-A-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
            'reorder_point' => 10, 'reorder_lead_time_days' => 7,
            'avg_daily_sales' => 3, 'default_supplier_id' => $supplier1->id,
            'track_inventory' => true,
        ]);

        $product2 = Product::create([
            'name' => 'Widget B', 'slug' => 'widget-b-'.uniqid(),
            'sku' => 'SKU-B-'.uniqid(),
            'purchase_price' => 8, 'selling_price' => 15,
            'reorder_point' => 5, 'reorder_lead_time_days' => 3,
            'avg_daily_sales' => 2, 'default_supplier_id' => $supplier2->id,
            'track_inventory' => true,
        ]);

        $response = $this->postJson('/api/v1/reorder-suggestions/create-draft-po', [
            'warehouse_id' => $warehouse->id,
            'items' => [
                [
                    'product_id' => $product1->id,
                    'quantity' => 26,
                    'unit_cost' => 5,
                    'supplier_id' => $supplier1->id,
                ],
                [
                    'product_id' => $product2->id,
                    'quantity' => 11,
                    'unit_cost' => 8,
                    'supplier_id' => $supplier2->id,
                ],
            ],
        ]);

        $response->assertCreated();
        $purchases = $response->json('data');
        $this->assertCount(2, $purchases);

        $supplierIds = array_map(fn ($p) => $p['supplier']['id'], $purchases);
        $this->assertContains($supplier1->id, $supplierIds);
        $this->assertContains($supplier2->id, $supplierIds);
    }
}
