<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\TaxRate;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * A return must reverse the *exact* tax that was charged, not recompute it —
 * otherwise a rate change between the sale and the return silently changes
 * the refund.
 */
class SaleReturnTaxTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_a_full_return_reverses_the_exact_tax_charged(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $group = $this->taxGroupWithComponents(['CGST' => 9, 'SGST' => 9]);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 50, 'selling_price' => 100, 'tax_group_id' => $group->id, 'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouse->id, 10, 50);

        $sale = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data');

        // 2 units @ 100 = 200 net; CGST 9% + SGST 9% = 36 total tax.
        $this->assertEquals(36.0, $sale['items'][0]['tax_amount']);

        // The rate changes *after* the sale — the return must not care.
        TaxRate::where('name', 'CGST')->update(['rate' => 20]);

        $return = $this->postJson("/api/v1/sales/{$sale['id']}/returns", [
            'items' => [['sale_item_id' => $sale['items'][0]['id'], 'quantity' => 1]],
        ])->assertCreated()->json('data');

        // Half the quantity returned -> half the original tax, at the *original* rate.
        $this->assertEquals(18.0, $return['tax_amount']);
        $this->assertEqualsCanonicalizing(['CGST', 'SGST'], collect($return['items'][0]['taxes'])->pluck('name')->all());
        $this->assertEquals(9.0, collect($return['items'][0]['taxes'])->firstWhere('name', 'CGST')['amount']);
    }
}
