<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SaleReturnTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_returning_items_restocks_and_is_capped_at_sold_quantity(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Mug', 'slug' => 'mug-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 3, 'selling_price' => 10,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 20, 3);

        // Sell 5.
        $sale = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 50]],
        ])->assertCreated()->json('data');

        $saleItemId = $sale['items'][0]['id'];

        // Return 2 → stock back to 17, refund 20.
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", [
            'items' => [['sale_item_id' => $saleItemId, 'quantity' => 2]],
        ])->assertCreated()->assertJsonPath('data.total', 20);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(17, $product->fresh()->totalStock(), 0.001);
        $this->assertDatabaseHas('stock_movements', ['type' => 'sale_return', 'direction' => 'in', 'quantity' => 2]);

        // Over-returning the remaining 3 by asking for 4 is rejected.
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", [
            'items' => [['sale_item_id' => $saleItemId, 'quantity' => 4]],
        ])->assertStatus(422);
    }
}
