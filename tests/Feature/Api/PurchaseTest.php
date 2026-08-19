<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_receiving_a_purchase_increases_stock_and_supplier_due(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = Product::create([
            'name' => 'Sugar 1kg', 'slug' => 'sugar-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => 2, 'selling_price' => 3,
        ]);
        $supplier = Supplier::create(['name' => 'Acme Supply']);

        $response = $this->postJson('/api/v1/purchases', [
            'warehouse_id' => $warehouse->id,
            'supplier_id' => $supplier->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 100, 'unit_cost' => 2.5],
            ],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.total', 250)
            ->assertJsonPath('data.due_amount', 150)
            ->assertJsonPath('data.payment_status', 'partial');

        $purchase = Purchase::with('items')->first();

        $this->postJson("/api/v1/purchases/{$purchase->id}/approve")->assertOk();

        $itemId = $purchase->items->first()->id;
        $this->postJson("/api/v1/purchases/{$purchase->id}/receive", [
            'items' => [['id' => $itemId, 'accepted_qty' => 100]],
        ])->assertOk();

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(100, $product->fresh()->totalStock(), 0.001);
        $this->assertEqualsWithDelta(2.5, (float) $product->fresh()->purchase_price, 0.001);
        $this->assertEqualsWithDelta(150, (float) $supplier->fresh()->balance, 0.001);
        $this->assertDatabaseHas('stock_movements', ['type' => 'purchase', 'direction' => 'in', 'quantity' => 100]);
    }
}
