<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ProductImageTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_upload_image_stores_file_and_returns_url(): void
    {
        Storage::fake('public');

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml',
            'purchase_price' => 5,
            'selling_price' => 8,
        ])->assertCreated();

        $product = Product::first();

        $file = UploadedFile::fake()->image('product.jpg', 200, 200)->size(100);

        $response = $this->postJson("/api/v1/products/{$product->id}/image", [
            'image' => $file,
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['url'],
            ]);

        $product->refresh();
        $this->assertNotNull($product->image_path);
        $this->assertStringContainsString('products/', $product->image_path);
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('public');

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml',
            'purchase_price' => 5,
            'selling_price' => 8,
        ])->assertCreated();

        $product = Product::first();

        $file = UploadedFile::fake()->image('product.jpg', 200, 200)->size(3000);

        $response = $this->postJson("/api/v1/products/{$product->id}/image", [
            'image' => $file,
        ]);

        $response->assertUnprocessable();
    }

    public function test_delete_removes_file_and_nulls_column(): void
    {
        Storage::fake('public');

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/products', [
            'name' => 'Cola 500ml',
            'purchase_price' => 5,
            'selling_price' => 8,
        ])->assertCreated();

        $product = Product::first();

        $file = UploadedFile::fake()->image('product.jpg', 200, 200)->size(100);

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'image' => $file,
        ])->assertOk();

        $product->refresh();
        $this->assertNotNull($product->image_path);

        $response = $this->deleteJson("/api/v1/products/{$product->id}/image");

        $response->assertOk()
            ->assertJson(['data' => ['url' => null]]);

        $product->refresh();
        $this->assertNull($product->image_path);
    }

    public function test_cross_tenant_product_upload_returns_404(): void
    {
        Storage::fake('public');

        $ownerA = $this->createTenant('a@example.test');
        Sanctum::actingAs($ownerA);

        $this->postJson('/api/v1/products', [
            'name' => 'Tenant A Product',
            'purchase_price' => 5,
            'selling_price' => 8,
        ])->assertCreated();

        $product = Product::first();

        $ownerB = $this->createTenant('b@example.test');
        Sanctum::actingAs($ownerB);

        $file = UploadedFile::fake()->image('product.jpg', 200, 200)->size(100);

        $this->postJson("/api/v1/products/{$product->id}/image", [
            'image' => $file,
        ])->assertNotFound();
    }
}
