<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockSerial;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ProductVariantTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(): Product
    {
        return Product::create([
            'name' => 'T-Shirt', 'slug' => 'shirt-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 10, 'selling_price' => 20,
        ]);
    }

    public function test_variants_can_be_listed_for_a_product(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $product = $this->seedProduct();
        $product->variants()->create(['name' => 'Red / L', 'sku' => 'SHIRT-RED-L']);

        $response = $this->getJson("/api/v1/products/{$product->id}/variants");

        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Red / L');
    }

    public function test_owner_can_create_a_stock_batch(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct();

        $response = $this->postJson('/api/v1/stock/batches', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'batch_number' => 'BATCH-001', 'quantity' => 50, 'expires_at' => now()->addMonths(6)->toDateString(),
        ]);

        $response->assertCreated()->assertJsonPath('data.batch_number', 'BATCH-001');
        $this->assertDatabaseHas('stock_batches', ['product_id' => $product->id, 'batch_number' => 'BATCH-001']);
    }

    public function test_batches_can_be_filtered_by_expiry(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct();

        StockBatch::create([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'batch_number' => 'EXPIRING', 'quantity' => 5, 'expires_at' => now()->addDays(2),
        ]);
        StockBatch::create([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'batch_number' => 'FAR-OUT', 'quantity' => 5, 'expires_at' => now()->addYear(),
        ]);

        $response = $this->getJson('/api/v1/stock/batches?expiring_before='.now()->addWeek()->toDateString());

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame('EXPIRING', $response->json('data.0.batch_number'));
    }

    public function test_stock_batch_rejects_a_product_belonging_to_another_tenant(): void
    {
        $ownerA = $this->createTenant('a@example.test');
        $productA = $this->seedProduct();

        $ownerB = $this->createTenant('b@example.test');
        $warehouseB = $this->warehouseFor($ownerB);
        Sanctum::actingAs($ownerB);

        $this->postJson('/api/v1/stock/batches', [
            'product_id' => $productA->id, 'warehouse_id' => $warehouseB->id,
            'batch_number' => 'X', 'quantity' => 1,
        ])->assertStatus(422)->assertJsonValidationErrors('product_id');
    }

    public function test_owner_can_register_a_serial_number(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct();

        $response = $this->postJson('/api/v1/stock/serials', [
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'SN-0001',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.serial_number', 'SN-0001')
            ->assertJsonPath('data.status', 'in_stock');
        $this->assertDatabaseHas('stock_serials', ['serial_number' => 'SN-0001', 'status' => 'in_stock']);
    }

    public function test_duplicate_serial_number_for_the_same_product_is_rejected(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct();

        StockSerial::create([
            'product_id' => $product->id, 'warehouse_id' => $warehouse->id,
            'serial_number' => 'SN-DUP', 'status' => 'in_stock',
        ]);

        $response = $this->getJson('/api/v1/stock/serials?serial_number=SN-DUP');
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_serials_can_be_filtered_by_status(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct();

        StockSerial::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'SN-A', 'status' => 'in_stock']);
        StockSerial::create(['product_id' => $product->id, 'warehouse_id' => $warehouse->id, 'serial_number' => 'SN-B', 'status' => 'sold']);

        $response = $this->getJson('/api/v1/stock/serials?status=sold');
        $response->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.serial_number', 'SN-B');
    }
}
