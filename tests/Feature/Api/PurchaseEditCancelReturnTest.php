<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PurchaseEditCancelReturnTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_edit_purchase_before_goods_receipt(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $warehouse = $this->warehouseFor($owner);

        $product = Product::create([
            'name' => 'Edit Me', 'slug' => 'edit-me-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
        ]);
        $supplier = Supplier::create(['name' => 'Test Supplier']);

        $response = $this->postJson('/api/v1/purchases', [
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 50, 'unit_cost' => 5.0],
            ],
            'payments' => [],
        ]);

        $response->assertCreated();

        $purchase = Purchase::with('items')->first();

        $this->assertEquals(PurchaseStatus::Draft, $purchase->status);

        $response = $this->putJson("/api/v1/purchases/{$purchase->id}", [
            'note' => 'Edited note',
        ]);

        $response->assertOk();

        $purchase = $purchase->fresh();
        $this->assertEquals('Edited note', $purchase->note);
    }

    public function test_cancel_purchase_reverses_stock(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $warehouse = $this->warehouseFor($owner);

        $product = Product::create([
            'name' => 'Cancel Me', 'slug' => 'cancel-me-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
        ]);
        $supplier = Supplier::create(['name' => 'Test Supplier']);

        $response = $this->postJson('/api/v1/purchases', [
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 50, 'unit_cost' => 5.0],
            ],
            'payments' => [],
        ]);

        $response->assertCreated();

        $purchase = Purchase::with('items')->first();

        $this->postJson("/api/v1/purchases/{$purchase->id}/approve")->assertOk();

        $this->assertEqualsWithDelta(0, $product->fresh()->totalStock(), 0.001);

        $this->postJson("/api/v1/purchases/{$purchase->id}/cancel")->assertOk();

        $this->assertEqualsWithDelta(0, $product->fresh()->totalStock(), 0.001);
        $this->assertEquals(PurchaseStatus::Cancelled, $purchase->fresh()->status);
    }

    public function test_return_creates_debit_note(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $warehouse = $this->warehouseFor($owner);

        $product = Product::create([
            'name' => 'Return Me', 'slug' => 'return-me-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
        ]);
        $supplier = Supplier::create(['name' => 'Test Supplier', 'balance' => 300]);

        $response = $this->postJson('/api/v1/purchases', [
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 50, 'unit_cost' => 5.0],
            ],
            'payments' => [],
        ]);

        $response->assertCreated();

        $purchase = Purchase::with('items')->first();

        $this->postJson("/api/v1/purchases/{$purchase->id}/approve")->assertOk();

        $response = $this->postJson("/api/v1/purchases/{$purchase->id}/return", [
            'note' => 'Returning goods',
        ]);

        $response->assertOk();

        $purchase = $purchase->fresh();
        $this->assertEquals(PurchaseStatus::Returned, $purchase->status);
        $this->assertEquals('Returning goods', $purchase->note);

        $supplier = $supplier->fresh();
        $this->assertEqualsWithDelta(50, (float) $supplier->balance, 0.001);
    }
}
