<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\Sale;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SaleCheckoutTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(int $ownerId, int $warehouseId, float $stock, float $price, float $tax = 0): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price, 'tax_rate' => $tax,
            'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)
            ->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    public function test_checkout_creates_sale_and_decrements_stock(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 20, price: 100, tax: 10);

        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 330]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.total', 330) // (100 * 3) + 10% tax
            ->assertJsonPath('data.change_amount', 0);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(17, $product->fresh()->totalStock(), 0.001);
        $this->assertDatabaseHas('stock_movements', ['type' => 'sale', 'direction' => 'out', 'quantity' => 3]);
    }

    public function test_checkout_rejects_insufficient_stock_and_rolls_back(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 2, price: 10);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 50]],
        ])->assertStatus(422)->assertJsonPath('error.available', 2);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(0, Sale::count());
        $this->assertEqualsWithDelta(2, $product->fresh()->totalStock(), 0.001); // unchanged
    }

    public function test_checkout_is_idempotent_with_idempotency_key(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 20, price: 10);

        $payload = [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ];

        $first = $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/v1/sales', $payload);
        $first->assertCreated();

        $second = $this->withHeader('Idempotency-Key', 'abc-123')->postJson('/api/v1/sales', $payload);
        $second->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, Sale::count());
        $this->assertEqualsWithDelta(18, $product->fresh()->totalStock(), 0.001);
    }

    public function test_invoice_pdf_can_be_streamed_and_downloaded(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 10, price: 50);

        $sale = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated()->json('data');

        $this->get("/api/v1/sales/{$sale['id']}/invoice")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $download = $this->get("/api/v1/sales/{$sale['id']}/invoice?download=1")->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('Content-Disposition'));
    }
}
