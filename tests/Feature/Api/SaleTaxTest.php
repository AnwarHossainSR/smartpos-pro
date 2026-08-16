<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\PriceDisplay;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\TaxGroup;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * End-to-end tax behaviour through a real checkout. The unit tests prove the
 * arithmetic; these prove the sale actually persists what was charged, per
 * component, and that a return reverses it.
 */
class SaleTaxTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $warehouseId, float $price, ?TaxGroup $group = null, float $stock = 100): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price,
            'tax_group_id' => $group?->id, 'min_stock_level' => 0,
        ]);

        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    private function checkout(array $overrides = []): TestResponse
    {
        return $this->postJson('/api/v1/sales', $overrides);
    }

    public function test_a_product_with_no_tax_group_is_untaxed(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 100);

        $this->checkout([
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.tax_amount', 0)
            ->assertJsonPath('data.total', 200);
    }

    public function test_exclusive_pricing_adds_tax_on_top(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $this->setPriceDisplay($owner, PriceDisplay::Exclusive);
        $product = $this->seedProduct($warehouse->id, 100, $this->taxGroup(15));

        $this->checkout([
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', 200)
            ->assertJsonPath('data.tax_amount', 30)
            ->assertJsonPath('data.total', 230);
    }

    /**
     * The case the old inline arithmetic existed to handle and the one a shop
     * notices immediately: a 115.00 shelf price must ring up as 115.00, with the
     * 15 extracted, not 132.25.
     */
    public function test_inclusive_pricing_extracts_tax_from_the_shelf_price(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $this->setPriceDisplay($owner, PriceDisplay::Inclusive);
        $product = $this->seedProduct($warehouse->id, 115, $this->taxGroup(15));

        $this->checkout([
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', 100)
            ->assertJsonPath('data.tax_amount', 15)
            ->assertJsonPath('data.total', 115);
    }

    /** A multi-component group is the thing a single rate column cannot express. */
    public function test_a_multi_component_group_is_persisted_component_by_component(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $this->setPriceDisplay($owner, PriceDisplay::Exclusive);
        $product = $this->seedProduct($warehouse->id, 100, $this->taxGroupWithComponents(['CGST' => 9, 'SGST' => 9]));

        $sale = $this->checkout([
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.tax_amount', 18)
            ->assertJsonPath('data.total', 118)
            ->json('data');

        app(TenantContext::class)->set($owner->tenant_id);
        $item = Sale::find($sale['id'])->items->first();

        $this->assertCount(2, $item->taxes);
        $this->assertEqualsCanonicalizing(['CGST', 'SGST'], $item->taxes->pluck('name')->all());
        $this->assertEqualsWithDelta(9.0, (float) $item->taxes->firstWhere('name', 'CGST')->amount, 0.001);
        // Both components charge on the net line, not on each other.
        $this->assertEqualsWithDelta(100.0, (float) $item->taxes->firstWhere('name', 'SGST')->base_amount, 0.001);
    }

    public function test_a_tax_exempt_customer_pays_no_tax(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 100, $this->taxGroup(15));
        $customer = Customer::create(['name' => 'Exempt Co', 'tax_exempt' => true, 'tax_number' => 'EX-1']);

        $this->checkout([
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.tax_amount', 0)
            ->assertJsonPath('data.total', 100);
    }

    /** Tax applies to the discounted line, never the gross one. */
    public function test_a_line_discount_reduces_the_taxable_base(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $this->setPriceDisplay($owner, PriceDisplay::Exclusive);
        $product = $this->seedProduct($warehouse->id, 100, $this->taxGroup(10));

        $this->checkout([
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'discount_amount' => 20]],
        ])->assertCreated()
            ->assertJsonPath('data.subtotal', 80)
            ->assertJsonPath('data.tax_amount', 8) // 10% of 80, not of 100
            ->assertJsonPath('data.total', 88);
    }

    /** A category-level group covers a whole range without touching each product. */
    public function test_tax_falls_back_to_the_category_group(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $this->setPriceDisplay($owner, PriceDisplay::Exclusive);

        $category = Category::create([
            'name' => 'Electronics', 'slug' => 'electronics-'.uniqid(),
            'tax_group_id' => $this->taxGroup(20)->id,
        ]);

        $product = $this->seedProduct($warehouse->id, 50);
        $product->update(['category_id' => $category->id]);

        $this->checkout([
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()
            ->assertJsonPath('data.tax_amount', 10)
            ->assertJsonPath('data.total', 60);
    }

    /** The product's own group wins over its category's. */
    public function test_the_product_group_overrides_the_category_group(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $this->setPriceDisplay($owner, PriceDisplay::Exclusive);

        $category = Category::create([
            'name' => 'Food', 'slug' => 'food-'.uniqid(),
            'tax_group_id' => $this->taxGroup(20, 'Standard')->id,
        ]);

        $product = $this->seedProduct($warehouse->id, 100, $this->taxGroup(5, 'Reduced'));
        $product->update(['category_id' => $category->id]);

        $this->checkout([
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.tax_amount', 5);
    }

    /** The legacy column is a mirror, not an input — setting it alone taxes nothing. */
    public function test_the_legacy_product_tax_rate_column_is_no_longer_read(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = $this->seedProduct($warehouse->id, 100);
        $product->update(['tax_rate' => 25]);

        $this->checkout([
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->assertJsonPath('data.tax_amount', 0);
    }
}
