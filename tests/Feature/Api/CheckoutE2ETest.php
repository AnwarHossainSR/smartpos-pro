<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class CheckoutE2ETest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $ownerId, string $warehouseId, float $stock, float $price, float $tax = 0): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price, 'tax_rate' => $tax,
            'tax_group_id' => $tax > 0 ? $this->taxGroup($tax)->id : null,
            'min_stock_level' => 0,
        ]);
        app(StockLedgerService::class)
            ->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    public function test_checkout_decrements_stock_and_posts_journal(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 10, price: 100, tax: 10);

        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 330]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.total', 330);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(7, $product->fresh()->totalStock(), 0.001);

        $this->assertDatabaseHas('journal_entries', [
            'reference' => 'SALE-'.$response->json('data.invoice_number'),
        ]);
    }

    public function test_checkout_updates_loyalty_on_sale(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $business = $owner->business;
        $business->update(['settings' => ['loyalty_earn_rate' => 1]]);
        $owner->refresh();

        $customer = Customer::create(['name' => 'Loyal Larry']);
        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 10, price: 100);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated();

        $this->getJson("/api/v1/customers/{$customer->id}/loyalty")
            ->assertOk()->assertJsonPath('data.points', 100);
    }

    public function test_checkout_honors_coupon_once_and_rejects_replay(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/coupons', [
            'code' => 'save10', 'type' => 'percentage', 'value' => 10, 'min_purchase' => 50,
            'usage_limit' => 1,
        ])->assertCreated();

        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 20, price: 100);

        // First checkout with coupon - should succeed. It carries the idempotency key
        // the replay below repeats: a replay is only a replay of the *same* key.
        $first = $this->withHeader('Idempotency-Key', 'coupon-test-1')
            ->postJson('/api/v1/sales', [
                'branch_id' => $owner->branch_id,
                'warehouse_id' => $warehouse->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payments' => [['method' => 'cash', 'amount' => 100]],
                'coupon_code' => 'SAVE10',
            ]);

        $first->assertCreated()->assertJsonPath('data.coupon_discount', 10);

        // Second checkout with same idempotency key - should replay
        $second = $this->withHeader('Idempotency-Key', 'coupon-test-1')
            ->postJson('/api/v1/sales', [
                'branch_id' => $owner->branch_id,
                'warehouse_id' => $warehouse->id,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
                'payments' => [['method' => 'cash', 'amount' => 100]],
                'coupon_code' => 'SAVE10',
            ]);

        $second->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, Sale::count());
        $this->assertEqualsWithDelta(19, $product->fresh()->totalStock(), 0.001);
    }

    public function test_checkout_idempotent_replay_no_double_stock_decrement(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($owner->id, $warehouse->id, stock: 20, price: 10);

        $payload = [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 30]],
        ];

        $first = $this->withHeader('Idempotency-Key', 'idem-key-001')->postJson('/api/v1/sales', $payload);
        $first->assertCreated();

        $second = $this->withHeader('Idempotency-Key', 'idem-key-001')->postJson('/api/v1/sales', $payload);
        $second->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, Sale::count());
        $this->assertEqualsWithDelta(17, $product->fresh()->totalStock(), 0.001);
    }
}
