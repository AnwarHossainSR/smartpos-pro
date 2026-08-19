<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ProductBarcodeTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_product_can_be_looked_up_by_barcode(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml',
            'purchase_price' => 5,
            'selling_price' => 8,
            'barcode' => '8901030123456',
        ])->assertCreated();

        $this->getJson('/api/v1/products/barcode/8901030123456')
            ->assertOk()
            ->assertJsonPath('data.name', 'Cola 500ml');
    }

    public function test_duplicate_barcode_within_same_tenant_returns_422(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml',
            'purchase_price' => 5,
            'selling_price' => 8,
            'barcode' => '8901030123456',
        ])->assertCreated();

        $this->postJson('/api/v1/products', [
            'name' => 'Cola 1L',
            'purchase_price' => 8,
            'selling_price' => 12,
            'barcode' => '8901030123456',
        ])->assertUnprocessable();
    }

    public function test_cross_tenant_barcode_lookup_returns_404(): void
    {
        $ownerA = $this->createTenant('a@example.test');
        Sanctum::actingAs($ownerA);

        $this->postJson('/api/v1/products', [
            'name' => 'Tenant A Product',
            'purchase_price' => 5,
            'selling_price' => 8,
            'barcode' => '8901030123456',
        ])->assertCreated();

        $ownerB = $this->createTenant('b@example.test');
        Sanctum::actingAs($ownerB);

        $this->getJson('/api/v1/products/barcode/8901030123456')->assertNotFound();
    }

    public function test_nonexistent_barcode_returns_404(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/products/barcode/does-not-exist')->assertNotFound();
    }

    public function test_search_matches_barcode(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml',
            'purchase_price' => 5,
            'selling_price' => 8,
            'barcode' => '8901030123456',
        ])->assertCreated();

        $this->postJson('/api/v1/products', [
            'name' => 'Orange Soda',
            'purchase_price' => 3,
            'selling_price' => 5,
            'barcode' => '5901234123457',
        ])->assertCreated();

        $this->getJson('/api/v1/products?search=8901030')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Cola 500ml');
    }
}
