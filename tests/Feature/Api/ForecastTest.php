<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ForecastTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_forecast_renders_for_top_movers(): void
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

        // Add sales history
        $days = 90;
        for ($i = 0; $i < $days; $i++) {
            $quantity = 3; // consistent daily sales
            $sale = Sale::create([
                'business_id' => $owner->business_id,
                'branch_id' => $owner->branch_id,
                'warehouse_id' => $warehouse->id,
                'customer_id' => null,
                'user_id' => $owner->id,
                'invoice_number' => 'FORECAST-'.uniqid(),
                'status' => 'completed',
                'payment_status' => 'paid',
                'subtotal' => 15,
                'total' => 15,
                'paid_amount' => 15,
                'due_amount' => 0,
                'sold_at' => now()->subDays($days - $i),
            ]);

            SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit_price' => 5,
                'line_total' => 15,
            ]);
        }

        $response = $this->getJson('/api/v1/forecasts?product_id='.$product->id);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('predicted_demand', $data[0]);
        $this->assertArrayHasKey('confidence', $data[0]);
        $this->assertArrayHasKey('trend', $data[0]);
        $this->assertGreaterThan(0, $data[0]['predicted_demand']);
    }

    public function test_forecast_drives_suggested_order_quantities(): void
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

        // Set low stock to trigger reorder suggestion
        app(StockLedgerService::class)->addOpeningStock(
            $product->id, $warehouse->id, 5
        );

        $response = $this->getJson('/api/v1/reorder-suggestions');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertArrayHasKey('forecast', $data[0]);
        $this->assertArrayHasKey('predicted_demand', $data[0]['forecast']);
        $this->assertArrayHasKey('trend', $data[0]['forecast']);
    }
}
