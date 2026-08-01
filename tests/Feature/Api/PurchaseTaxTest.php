<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\PriceDisplay;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * Input tax on a purchase is resolved by the same engine a sale uses, from the
 * product's tax group — never client-supplied — and always against the net
 * cost, regardless of how this tenant prices *sales* to its own customers.
 */
class PurchaseTaxTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_input_tax_is_resolved_from_the_product_tax_group_not_the_client(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        // Even a tenant that shows tax-inclusive prices to its customers still
        // records purchase cost net of tax.
        $this->setPriceDisplay($owner, PriceDisplay::Inclusive);
        $group = $this->taxGroup(15);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 50, 'selling_price' => 100, 'tax_group_id' => $group->id, 'min_stock_level' => 0,
        ]);

        $purchase = $this->postJson('/api/v1/purchases', [
            'warehouse_id' => $warehouse->id,
            // A client-supplied tax_amount, if sent, must be ignored entirely.
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_cost' => 50, 'tax_amount' => 999]],
        ])->assertCreated()->json('data');

        $this->assertEquals(30.0, $purchase['tax_amount']); // 15% of 200
        $this->assertEquals(230.0, $purchase['total']);
    }

    public function test_a_product_with_no_tax_group_has_no_input_tax(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 50, 'selling_price' => 100, 'min_stock_level' => 0,
        ]);

        $purchase = $this->postJson('/api/v1/purchases', [
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_cost' => 50]],
        ])->assertCreated()->json('data');

        $this->assertEquals(0.0, $purchase['tax_amount']);
    }
}
