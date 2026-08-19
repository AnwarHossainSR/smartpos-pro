<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PurchaseWorkflowTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function createDraftPurchase(User $owner, Product $product, Supplier $supplier): Purchase
    {
        $warehouse = $this->warehouseFor($owner);

        $response = $this->postJson('/api/v1/purchases', [
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 100, 'unit_cost' => 2.5],
            ],
            'payments' => [],
        ]);

        $response->assertCreated();

        return Purchase::with('items')->first();
    }

    public function test_draft_to_approve_to_partial_receive_to_full_receive_lifecycle(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 2, 'selling_price' => 4,
        ]);
        $supplier = Supplier::create(['name' => 'Acme Corp']);

        $purchase = $this->createDraftPurchase($owner, $product, $supplier);

        $this->assertEquals(PurchaseStatus::Draft, $purchase->status);
        $this->assertNull($purchase->approved_at);
        $this->assertNull($purchase->received_at);
        $this->assertEqualsWithDelta(0, $product->fresh()->totalStock(), 0.001);

        $response = $this->postJson("/api/v1/purchases/{$purchase->id}/approve");
        $response->assertOk();

        $purchase = $purchase->fresh();
        $this->assertEquals(PurchaseStatus::Approved, $purchase->status);
        $this->assertNotNull($purchase->approved_at);
        $this->assertEqualsWithDelta(0, $product->fresh()->totalStock(), 0.001);

        $itemId = $purchase->items->first()->id;
        $response = $this->postJson("/api/v1/purchases/{$purchase->id}/receive", [
            'items' => [['id' => $itemId, 'accepted_qty' => 60]],
        ]);
        $response->assertOk();

        $purchase = $purchase->fresh();
        $this->assertEquals(PurchaseStatus::Partial, $purchase->status);
        $this->assertEqualsWithDelta(60, $product->fresh()->totalStock(), 0.001);

        $response = $this->postJson("/api/v1/purchases/{$purchase->id}/receive", [
            'items' => [['id' => $itemId, 'accepted_qty' => 100]],
        ]);
        $response->assertOk();

        $purchase = $purchase->fresh();
        $this->assertEquals(PurchaseStatus::Received, $purchase->status);
        $this->assertNotNull($purchase->received_at);
        $this->assertEqualsWithDelta(100, $product->fresh()->totalStock(), 0.001);
    }

    public function test_stock_only_moves_on_receive(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $product = Product::create([
            'name' => 'Gadget', 'slug' => 'gadget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 10, 'selling_price' => 20,
        ]);
        $supplier = Supplier::create(['name' => 'Widget Inc']);

        $purchase = $this->createDraftPurchase($owner, $product, $supplier);

        $this->assertEqualsWithDelta(0, $product->fresh()->totalStock(), 0.001);

        $this->postJson("/api/v1/purchases/{$purchase->id}/approve")->assertOk();

        $this->assertEqualsWithDelta(0, $product->fresh()->totalStock(), 0.001);

        $itemId = $purchase->items->first()->id;
        $this->postJson("/api/v1/purchases/{$purchase->id}/receive", [
            'items' => [['id' => $itemId, 'accepted_qty' => 50]],
        ])->assertOk();

        $this->assertEqualsWithDelta(50, $product->fresh()->totalStock(), 0.001);
    }

    public function test_cancel_guard_cannot_cancel_received(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $product = Product::create([
            'name' => 'Thing', 'slug' => 'thing-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10,
        ]);
        $supplier = Supplier::create(['name' => 'Parts Co']);

        $purchase = $this->createDraftPurchase($owner, $product, $supplier);
        $this->postJson("/api/v1/purchases/{$purchase->id}/approve")->assertOk();

        $itemId = $purchase->items->first()->id;
        $this->postJson("/api/v1/purchases/{$purchase->id}/receive", [
            'items' => [['id' => $itemId, 'accepted_qty' => 100]],
        ])->assertOk();

        $response = $this->postJson("/api/v1/purchases/{$purchase->id}/cancel");
        $response->assertStatus(422);

        $this->assertEquals(PurchaseStatus::Received, $purchase->fresh()->status);
    }

    public function test_oversell_of_accepted_qty_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $product = Product::create([
            'name' => 'Doohickey', 'slug' => 'doohickey-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 3, 'selling_price' => 6,
        ]);
        $supplier = Supplier::create(['name' => 'Supply Ltd']);

        $purchase = $this->createDraftPurchase($owner, $product, $supplier);
        $this->postJson("/api/v1/purchases/{$purchase->id}/approve")->assertOk();

        $itemId = $purchase->items->first()->id;
        $response = $this->postJson("/api/v1/purchases/{$purchase->id}/receive", [
            'items' => [['id' => $itemId, 'accepted_qty' => 150]],
        ]);

        $response->assertStatus(422);
        $this->assertEquals(PurchaseStatus::Approved, $purchase->fresh()->status);
    }

    public function test_supplier_balance_updated_on_receive(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $product = Product::create([
            'name' => 'Item', 'slug' => 'item-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 10, 'selling_price' => 20,
        ]);
        $supplier = Supplier::create(['name' => 'Vendor', 'balance' => 0]);

        $purchase = $this->createDraftPurchase($owner, $product, $supplier);
        $this->postJson("/api/v1/purchases/{$purchase->id}/approve")->assertOk();

        $itemId = $purchase->items->first()->id;
        $this->postJson("/api/v1/purchases/{$purchase->id}/receive", [
            'items' => [['id' => $itemId, 'accepted_qty' => 100]],
        ])->assertOk();

        $this->assertEqualsWithDelta(250, (float) $supplier->fresh()->balance, 0.001);
    }
}
