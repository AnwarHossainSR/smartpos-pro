<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class QuotationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(int $warehouseId, float $stock, float $price): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price,
        ]);
        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    public function test_owner_can_create_a_quotation(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($this->warehouseFor($owner)->id, 10, 50);

        $response = $this->postJson('/api/v1/quotations', [
            'branch_id' => $owner->branch_id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertEquals(100, $response->json('data.total'));
    }

    public function test_quotation_rejects_a_product_belonging_to_another_tenant(): void
    {
        $ownerA = $this->createTenant('a@example.test');
        $productA = $this->seedProduct($this->warehouseFor($ownerA)->id, 10, 50);

        $ownerB = $this->createTenant('b@example.test');
        Sanctum::actingAs($ownerB);

        $this->postJson('/api/v1/quotations', [
            'branch_id' => $ownerB->branch_id,
            'items' => [['product_id' => $productA->id, 'quantity' => 1]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.product_id');
    }

    public function test_quotation_can_be_converted_to_a_sale(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 10, 50);

        $quotation = $this->postJson('/api/v1/quotations', [
            'branch_id' => $owner->branch_id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data');

        $response = $this->postJson("/api/v1/quotations/{$quotation['id']}/convert", [
            'warehouse_id' => $warehouse->id,
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ]);

        $response->assertCreated();
        $this->assertEquals(100, $response->json('data.total'));
        $this->assertSame('converted', Quotation::find($quotation['id'])->status);
        $this->assertEqualsWithDelta(8, $product->fresh()->totalStock(), 0.001);
    }

    public function test_an_expired_quotation_cannot_be_converted(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 10, 50);

        $quotation = Quotation::create([
            'branch_id' => $owner->branch_id, 'user_id' => $owner->id,
            'reference_number' => 'QUO-EXPIRED', 'status' => 'sent',
            'subtotal' => 50, 'total' => 50, 'valid_until' => now()->subDay(),
        ]);
        $quotation->items()->create(['product_id' => $product->id, 'quantity' => 1, 'unit_price' => 50, 'subtotal' => 50]);

        $this->postJson("/api/v1/quotations/{$quotation->id}/convert", ['warehouse_id' => $warehouse->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('valid_until');
    }

    public function test_cashier_without_sales_delete_permission_cannot_delete_quotations(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($this->warehouseFor($owner)->id, 10, 50);

        $quotation = $this->postJson('/api/v1/quotations', [
            'branch_id' => $owner->branch_id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data');

        $cashier = User::create([
            'tenant_id' => $owner->tenant_id, 'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test', 'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));
        $this->deleteJson("/api/v1/quotations/{$quotation['id']}")->assertForbidden();
    }
}
