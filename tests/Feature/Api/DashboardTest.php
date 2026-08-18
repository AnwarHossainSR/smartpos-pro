<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $warehouseId, float $stock, float $minStockLevel): Product
    {
        $product = Product::create([
            'name' => 'Widget-'.uniqid(),
            'slug' => 'widget-'.uniqid(),
            'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 10,
            'selling_price' => 50,
            'min_stock_level' => $minStockLevel,
        ]);

        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, $stock, 10);

        return $product;
    }

    /**
     * Sales are written straight through the model rather than the checkout endpoint: the
     * dashboard is a read surface, and building the rows here is what lets the test pin
     * `sold_at` to a specific week and keep the stock levels the low-stock block asserts on.
     */
    private function seedSale(
        User $owner,
        string $warehouseId,
        Product $product,
        string $invoice,
        float $total,
        float $quantity,
        \DateTimeInterface $soldAt,
        ?Customer $customer = null,
    ): Sale {
        $sale = Sale::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouseId,
            'customer_id' => $customer?->id,
            'user_id' => $owner->id,
            'invoice_number' => $invoice,
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => $total,
            'due_amount' => 0,
            'sold_at' => $soldAt,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $total / $quantity,
            'line_total' => $total,
        ]);

        return $sale;
    }

    public function test_summary_includes_dashboard_v2_fields(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        // Both below their reorder point; the widget is further below it, so it leads.
        $widget = $this->seedProduct($warehouse->id, stock: 2, minStockLevel: 10);
        $gadget = $this->seedProduct($warehouse->id, stock: 5, minStockLevel: 6);
        $customer = Customer::create(['name' => 'Ada Lovelace']);

        // 150 this week against 100 last week — a clean +50%.
        $this->seedSale($owner, $warehouse->id, $widget, 'INV-THIS', 150, 3, now(), $customer);
        $this->seedSale($owner, $warehouse->id, $gadget, 'INV-LAST', 100, 1, now()->startOfWeek()->subDays(2));

        $response = $this->getJson('/api/v1/dashboard/summary')->assertOk();

        // week_comparison — half-open ranges, so neither sale is double counted.
        $response->assertJsonPath('data.week_comparison.this_week', 150)
            ->assertJsonPath('data.week_comparison.last_week', 100)
            ->assertJsonPath('data.week_comparison.change_pct', 50);

        // top_products — the widget out-earns the gadget over the last 7 days.
        $response->assertJsonPath('data.top_products.0.name', $widget->name)
            ->assertJsonPath('data.top_products.0.quantity', 3)
            ->assertJsonPath('data.top_products.0.revenue', 150);

        // recent_sales — newest first, customer name resolved via the join.
        $response->assertJsonCount(2, 'data.recent_sales')
            ->assertJsonPath('data.recent_sales.0.invoice_number', 'INV-THIS')
            ->assertJsonPath('data.recent_sales.0.total', 150)
            ->assertJsonPath('data.recent_sales.0.payment_status', 'paid')
            ->assertJsonPath('data.recent_sales.0.customer_name', 'Ada Lovelace')
            ->assertJsonPath('data.recent_sales.1.customer_name', null);
        $this->assertNotNull($response->json('data.recent_sales.0.sold_at'));

        // low_stock_items — worst shortfall first, warehouse name included.
        $response->assertJsonCount(2, 'data.low_stock_items')
            ->assertJsonPath('data.low_stock_items.0.name', $widget->name)
            ->assertJsonPath('data.low_stock_items.0.warehouse', $warehouse->name)
            ->assertJsonPath('data.low_stock_items.0.quantity', 2)
            ->assertJsonPath('data.low_stock_items.0.min_stock_level', 10)
            ->assertJsonPath('data.low_stock_items.1.name', $gadget->name);
    }

    public function test_summary_is_empty_but_valid_for_a_tenant_with_no_sales(): void
    {
        $owner = $this->createTenant('fresh-owner@example.test');
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonCount(0, 'data.top_products')
            ->assertJsonCount(0, 'data.recent_sales')
            ->assertJsonCount(0, 'data.low_stock_items')
            ->assertJsonPath('data.week_comparison.this_week', 0)
            ->assertJsonPath('data.week_comparison.last_week', 0)
            // No baseline and no revenue: flat, not a division by zero.
            ->assertJsonPath('data.week_comparison.change_pct', 0);
    }

    public function test_summary_does_not_leak_another_tenants_sales(): void
    {
        $other = $this->createTenant('other-owner@example.test');
        $otherWarehouse = $this->warehouseFor($other);
        $otherProduct = $this->seedProduct($otherWarehouse->id, stock: 1, minStockLevel: 99);
        $this->seedSale($other, $otherWarehouse->id, $otherProduct, 'INV-OTHER', 500, 1, now());

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/dashboard/summary')
            ->assertOk()
            ->assertJsonCount(0, 'data.top_products')
            ->assertJsonCount(0, 'data.recent_sales')
            ->assertJsonCount(0, 'data.low_stock_items')
            ->assertJsonPath('data.week_comparison.this_week', 0);
    }
}
