<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_owner_can_create_a_product_with_opening_stock(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml',
            'purchase_price' => 5,
            'selling_price' => 8,
            'tax_rate' => 10,
            'opening_stock' => ['warehouse_id' => $warehouse->id, 'quantity' => 50],
        ]);

        $response->assertCreated()->assertJsonPath('data.name', 'Cola 500ml');

        $product = Product::first();
        $this->assertEqualsWithDelta(50, $product->totalStock(), 0.001);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'type' => 'opening',
            'direction' => 'in',
        ]);
    }

    public function test_products_are_isolated_between_tenants(): void
    {
        // Tenant A creates a product.
        $ownerA = $this->createTenant('a@example.test');
        Sanctum::actingAs($ownerA);
        $this->postJson('/api/v1/products', [
            'name' => 'Tenant A Product', 'purchase_price' => 1, 'selling_price' => 2,
        ])->assertCreated();

        // Tenant B must not see it — neither through the API nor the model.
        $ownerB = $this->createTenant('b@example.test');
        Sanctum::actingAs($ownerB);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(0, 'data');

        app(TenantContext::class)->set($ownerB->tenant_id);
        $this->assertSame(0, Product::count());

        app(TenantContext::class)->set($ownerA->tenant_id);
        $this->assertSame(1, Product::count());
    }

    public function test_product_can_be_looked_up_by_barcode(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml', 'purchase_price' => 5, 'selling_price' => 8, 'barcode' => '8901030123456',
        ])->assertCreated();

        $this->getJson('/api/v1/products/barcode/8901030123456')
            ->assertOk()
            ->assertJsonPath('data.name', 'Cola 500ml');

        $this->getJson('/api/v1/products/barcode/does-not-exist')->assertNotFound();
    }

    public function test_search_filters_products_by_name(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', ['name' => 'Apple Juice', 'purchase_price' => 1, 'selling_price' => 2])->assertCreated();
        $this->postJson('/api/v1/products', ['name' => 'Orange Soda', 'purchase_price' => 1, 'selling_price' => 2])->assertCreated();

        $this->getJson('/api/v1/products?search=Apple')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Apple Juice');
    }
}
