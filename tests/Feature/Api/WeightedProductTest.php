<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class WeightedProductTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_create_weighed_product_with_price_per_unit(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Bananas',
            'purchase_price' => 1.50,
            'selling_price' => 2.99,
            'is_weighed' => true,
            'weight_unit' => 'kg',
            'price_per_unit' => 2.99,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Bananas')
            ->assertJsonPath('data.is_weighed', true)
            ->assertJsonPath('data.weight_unit', 'kg')
            ->assertJsonPath('data.price_per_unit', 2.99);

        $this->assertDatabaseHas('products', [
            'is_weighed' => 1,
            'weight_unit' => 'kg',
            'price_per_unit' => 2.99,
        ]);
    }

    public function test_parse_weighted_barcode_gs1_format(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Apples',
            'purchase_price' => 2.00,
            'selling_price' => 3.50,
            'is_weighed' => true,
            'weight_unit' => 'kg',
            'price_per_unit' => 3.50,
            'barcode' => '2112345',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/products/barcode/211234500750')
            ->assertOk();

        $this->assertEquals('Apples', $response->json('data.name'));
        $this->assertEquals(0.75, $response->json('parsed_weight'));
    }

    public function test_parse_weighted_barcode_itf_format(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Chicken Breast',
            'purchase_price' => 5.00,
            'selling_price' => 8.99,
            'is_weighed' => true,
            'weight_unit' => 'kg',
            'price_per_unit' => 8.99,
            'barcode' => '12345678',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/products/barcode/12345678012500')
            ->assertOk();

        $this->assertEquals('Chicken Breast', $response->json('data.name'));
        $this->assertEquals(12.5, $response->json('parsed_weight'));
    }

    public function test_non_weighed_product_barcode_works_normally(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml',
            'purchase_price' => 5,
            'selling_price' => 8,
            'barcode' => '8901030123456',
        ])->assertCreated();

        $response = $this->getJson('/api/v1/products/barcode/8901030123456')
            ->assertOk()
            ->assertJsonPath('data.name', 'Cola 500ml');

        $this->assertNull($response->json('parsed_weight'));
    }

    public function test_weighed_fields_can_be_updated(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Beef Mince',
            'purchase_price' => 6.00,
            'selling_price' => 10.00,
        ])->assertCreated();

        $product = Product::first();

        $this->putJson("/api/v1/products/{$product->id}", [
            'is_weighed' => true,
            'weight_unit' => 'lb',
            'price_per_unit' => 4.54,
        ])->assertOk();

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'is_weighed' => 1,
            'weight_unit' => 'lb',
            'price_per_unit' => 4.54,
        ]);
    }
}
