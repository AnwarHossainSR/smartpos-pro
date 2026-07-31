<?php

namespace Tests\Feature\Api;

use App\Models\IdempotencyKey;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * Covers the guards added after the API audit: the idempotency mutex, the
 * variant/product integrity check, page-size ceilings and date-range filtering.
 */
class ApiHardeningTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(int $warehouseId, float $stock = 20, float $price = 10): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price, 'min_stock_level' => 0,
        ]);

        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    // ---- Idempotency mutex ----

    public function test_a_key_still_in_flight_is_rejected_instead_of_running_twice(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        $key = 'in-flight-'.uniqid();
        $payload = [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ];

        // Stand in for a checkout that is mid-flight: the claim row exists, but no
        // outcome has been recorded yet. This is exactly the state a client hits when
        // it times out and retries before the first request has committed.
        IdempotencyKey::create([
            'tenant_id' => $owner->tenant_id,
            'key' => $key,
            'method' => 'POST',
            'path' => 'api/v1/sales',
            'payload_hash' => hash('sha256', json_encode($payload)),
        ]);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/sales', $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'A request with this idempotency key is already in progress. Retry shortly.');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(0, Sale::count());
        $this->assertEqualsWithDelta(20, $product->fresh()->totalStock(), 0.001);
    }

    public function test_an_abandoned_claim_is_reclaimed_once_it_goes_stale(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        $key = 'stale-'.uniqid();
        $payload = [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ];

        // A worker killed mid-request leaves this behind; it must not lock the key out
        // forever, or the client can never complete the sale.
        $claim = IdempotencyKey::create([
            'tenant_id' => $owner->tenant_id,
            'key' => $key,
            'method' => 'POST',
            'path' => 'api/v1/sales',
            'payload_hash' => hash('sha256', json_encode($payload)),
        ]);
        $claim->forceFill(['updated_at' => now()->subHour()])->save();

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/sales', $payload)
            ->assertCreated();

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, Sale::count());
    }

    public function test_a_completed_key_still_replays_its_original_response(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        $key = 'replay-'.uniqid();
        $payload = [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ];

        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/sales', $payload)->assertCreated();

        $this->withHeader('Idempotency-Key', $key)
            ->postJson('/api/v1/sales', $payload)
            ->assertCreated()
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('data.id', $first->json('data.id'));

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(1, Sale::count());
    }

    // ---- Variant / product integrity ----

    public function test_a_sale_line_cannot_pair_a_product_with_another_products_variant(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $sold = $this->seedProduct($warehouse->id);
        $other = $this->seedProduct($warehouse->id);

        $foreignVariant = ProductVariant::create([
            'product_id' => $other->id, 'name' => 'Large', 'sku' => 'VAR-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10, 'is_active' => true,
        ]);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $sold->id,
                'variant_id' => $foreignVariant->id,
                'quantity' => 1,
            ]],
        ])->assertStatus(422)->assertJsonValidationErrors('items.0.variant_id');

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(0, Sale::count());
    }

    public function test_a_sale_line_accepts_a_variant_of_its_own_product(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = $this->seedProduct($warehouse->id);
        $variant = ProductVariant::create([
            'product_id' => $product->id, 'name' => 'Large', 'sku' => 'VAR-'.uniqid(),
            'purchase_price' => 5, 'selling_price' => 10, 'is_active' => true,
        ]);

        app(StockLedgerService::class)->record([
            'product_id' => $product->id, 'variant_id' => $variant->id,
            'warehouse_id' => $warehouse->id, 'direction' => 'in',
            'quantity' => 5, 'unit_cost' => 5, 'type' => 'opening',
        ]);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [[
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'quantity' => 1,
            ]],
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertCreated();
    }

    // ---- Page-size ceiling ----

    public function test_per_page_is_capped_so_one_request_cannot_pull_a_whole_table(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $this->seedProduct($warehouse->id);

        $this->getJson('/api/v1/products?per_page=1000000')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 200);
    }

    public function test_a_nonsensical_per_page_falls_back_to_the_endpoint_default(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/products?per_page=0')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25);

        $this->getJson('/api/v1/products?per_page=-5')
            ->assertOk()
            ->assertJsonPath('meta.per_page', 25);
    }

    // ---- Date-range filtering ----

    public function test_the_sales_date_filter_is_inclusive_of_both_end_days(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        foreach (['2026-03-01 00:00:00', '2026-03-05 23:30:00', '2026-03-09 12:00:00'] as $i => $soldAt) {
            Sale::forceCreate([
                'tenant_id' => $owner->tenant_id, 'business_id' => $owner->business_id,
                'branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id,
                'user_id' => $owner->id, 'invoice_number' => 'INV-D-'.$i,
                'status' => 'completed', 'payment_status' => 'paid',
                'total' => 100, 'sold_at' => $soldAt,
            ]);
        }

        // 03-05 sits late in its day: an exclusive upper bound would drop it.
        $this->getJson('/api/v1/sales?from=2026-03-01&to=2026-03-05')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_malformed_date_filter_is_rejected_rather_than_ignored(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/sales?from=not-a-date')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    }

    public function test_reports_refuse_an_unbounded_range(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/reports/sales-summary?from=1970-01-01&to=2026-12-31')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');

        $this->getJson('/api/v1/reports/sales-summary?from=2026-03-31&to=2026-03-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors('from');
    }

    public function test_sales_summary_still_totals_correctly_after_the_single_pass_rewrite(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, stock: 50, price: 10);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 3]],
            'payments' => [['method' => 'cash', 'amount' => 30]],
        ])->assertCreated();

        $this->getJson('/api/v1/reports/sales-summary')
            ->assertOk()
            ->assertJsonPath('data.invoices', 1)
            ->assertJsonPath('data.gross_sales', 30)
            ->assertJsonPath('data.net_sales', 30)
            ->assertJsonPath('data.amount_paid', 30)
            ->assertJsonPath('data.amount_due', 0);
    }

    public function test_sales_summary_reports_zeroes_for_an_empty_period(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/reports/sales-summary')
            ->assertOk()
            ->assertJsonPath('data.invoices', 0)
            ->assertJsonPath('data.gross_sales', 0)
            ->assertJsonPath('data.net_sales', 0);
    }
}
