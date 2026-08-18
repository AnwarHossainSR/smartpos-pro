<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\SaleStatus;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Services\Sales\InvoiceNumberService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * G18 — per-tenant gapless invoice numbering.
 *
 * Note on concurrency: the acceptance criterion is "two concurrent checkouts
 * produce sequential numbers with no gaps". The suite runs on in-memory SQLite,
 * which has no second connection to the same database and no `SELECT ... FOR
 * UPDATE`, so genuinely parallel transactions cannot be staged here. What is
 * asserted instead is everything the locking path is there to guarantee and
 * that *is* observable on SQLite: the counter is read-and-incremented inside
 * the checkout transaction, a checkout that rolls back returns its number, and
 * a run of checkouts is contiguous. On MySQL the `lockForUpdate()` in
 * {@see InvoiceNumberService::next()} is what serialises the same path.
 */
class InvoiceNumberingTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $warehouseId, float $stock = 100, float $price = 10): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price, 'min_stock_level' => 0,
        ]);

        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, $stock, $price / 2);

        return $product;
    }

    /** @return array<string, mixed> */
    private function checkout(User $owner, Product $product, string $warehouseId, float $quantity = 1, ?string $status = null): array
    {
        $payload = [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouseId,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
            'payments' => [['method' => 'cash', 'amount' => 10 * $quantity]],
        ];

        if ($status !== null) {
            $payload['status'] = $status;
        }

        return $this->postJson('/api/v1/sales', $payload)->assertCreated()->json('data');
    }

    private function sequenceFor(User $owner): int
    {
        return (int) Tenant::findOrFail($owner->tenant_id)->invoice_sequence;
    }

    public function test_successive_checkouts_produce_a_gapless_sequence(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        $numbers = [];

        for ($i = 0; $i < 5; $i++) {
            $numbers[] = $this->checkout($owner, $product, $warehouse->id)['invoice_number'];
        }

        $this->assertSame(['INV-1', 'INV-2', 'INV-3', 'INV-4', 'INV-5'], $numbers);
        $this->assertSame(5, $this->sequenceFor($owner));
    }

    public function test_numbering_is_independent_per_tenant(): void
    {
        $ownerA = $this->createTenant('a@example.test');
        $warehouseA = $this->warehouseFor($ownerA);
        Sanctum::actingAs($ownerA);
        $productA = $this->seedProduct($warehouseA->id);

        $this->assertSame('INV-1', $this->checkout($ownerA, $productA, $warehouseA->id)['invoice_number']);
        $this->assertSame('INV-2', $this->checkout($ownerA, $productA, $warehouseA->id)['invoice_number']);

        $ownerB = $this->createTenant('b@example.test');
        $warehouseB = $this->warehouseFor($ownerB);
        Sanctum::actingAs($ownerB);
        $productB = $this->seedProduct($warehouseB->id);

        // Tenant B starts its own run at 1 — it neither sees nor advances A's counter.
        $this->assertSame('INV-1', $this->checkout($ownerB, $productB, $warehouseB->id)['invoice_number']);
        $this->assertSame('INV-2', $this->checkout($ownerB, $productB, $warehouseB->id)['invoice_number']);

        $this->assertSame(2, $this->sequenceFor($ownerA));
        $this->assertSame(2, $this->sequenceFor($ownerB));
    }

    /**
     * The locking path, asserted deterministically: every number is taken inside
     * the checkout transaction, so a run of checkouts is contiguous and the
     * counter always equals the count of issued numbers — the invariant that a
     * lost update between two concurrent checkouts would break.
     */
    public function test_numbers_are_taken_inside_the_transaction_and_stay_contiguous(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        $numbers = app(InvoiceNumberService::class);

        $issued = [];

        for ($i = 0; $i < 4; $i++) {
            $issued[] = DB::transaction(fn () => $numbers->next($owner->tenant_id, $owner->business));
        }

        $this->assertSame(['INV-1', 'INV-2', 'INV-3', 'INV-4'], $issued);
        $this->assertSame(4, $this->sequenceFor($owner));

        // And two numbers taken within one transaction never collide.
        [$first, $second] = DB::transaction(fn () => [
            $numbers->next($owner->tenant_id, $owner->business),
            $numbers->next($owner->tenant_id, $owner->business),
        ]);

        $this->assertSame('INV-5', $first);
        $this->assertSame('INV-6', $second);
    }

    public function test_a_rolled_back_checkout_does_not_burn_a_number(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, stock: 5);

        $this->assertSame('INV-1', $this->checkout($owner, $product, $warehouse->id)['invoice_number']);

        // Fails on stock and rolls the whole transaction back — including the increment.
        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 500]],
            'payments' => [['method' => 'cash', 'amount' => 5000]],
        ])->assertStatus(422);

        $this->assertSame(1, $this->sequenceFor($owner));
        $this->assertSame('INV-2', $this->checkout($owner, $product, $warehouse->id)['invoice_number']);
    }

    public function test_a_held_sale_gets_no_number_until_it_is_resumed(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        $held = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'status' => 'held',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data');

        $this->assertNull($held['invoice_number']);
        $this->assertSame(0, $this->sequenceFor($owner));

        $resumed = $this->postJson("/api/v1/sales/{$held['id']}/resume", [
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ])->assertOk()->json('data');

        $this->assertSame('completed', $resumed['status']);
        $this->assertSame('INV-1', $resumed['invoice_number']);
        $this->assertSame(1, $this->sequenceFor($owner));
    }

    public function test_a_parked_cart_never_takes_a_number_out_of_the_run(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        $held = $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'status' => 'held',
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
        ])->assertCreated()->json('data');

        // Two customers checked out while the first cart sat parked at the till.
        $this->assertSame('INV-1', $this->checkout($owner, $product, $warehouse->id)['invoice_number']);
        $this->assertSame('INV-2', $this->checkout($owner, $product, $warehouse->id)['invoice_number']);

        $resumed = $this->postJson("/api/v1/sales/{$held['id']}/resume", [
            'payments' => [['method' => 'cash', 'amount' => 10]],
        ])->assertOk()->json('data');

        $this->assertSame('INV-3', $resumed['invoice_number']);
    }

    public function test_quotation_conversion_shares_the_same_sequence(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        $this->assertSame('INV-1', $this->checkout($owner, $product, $warehouse->id)['invoice_number']);

        $quotation = $this->postJson('/api/v1/quotations', [
            'branch_id' => $owner->branch_id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->assertCreated()->json('data');

        // Quoting alone issues nothing — only the conversion does.
        $this->assertSame(1, $this->sequenceFor($owner));

        $converted = $this->postJson("/api/v1/quotations/{$quotation['id']}/convert", [
            'warehouse_id' => $warehouse->id,
            'payments' => [['method' => 'cash', 'amount' => 20]],
        ])->assertCreated()->json('data');

        $this->assertSame('INV-2', $converted['invoice_number']);
        $this->assertSame(2, $this->sequenceFor($owner));
    }

    public function test_the_prefix_comes_from_business_settings(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        $owner->business->update(['settings' => ['invoice_prefix' => 'SP/2026-']]);
        $owner->unsetRelation('business');

        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        $this->assertSame('SP/2026-1', $this->checkout($owner, $product, $warehouse->id)['invoice_number']);
        $this->assertSame('SP/2026-2', $this->checkout($owner, $product, $warehouse->id)['invoice_number']);
    }

    public function test_backfill_numbers_unnumbered_sales_and_leaves_existing_numbers_alone(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id);

        // A sale numbered the old way, which must survive the backfill untouched.
        $legacy = Sale::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'invoice_number' => 'INV-LEGACY01',
            'status' => SaleStatus::Completed,
            'total' => 10,
            'paid_amount' => 10,
            'sold_at' => now()->subDay(),
        ]);

        // Two sales that predate numbering entirely, plus a parked cart that must stay blank.
        $unnumbered = collect(['a', 'b'])->map(fn () => Sale::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'invoice_number' => null,
            'status' => SaleStatus::Completed,
            'total' => 10,
            'paid_amount' => 10,
            'sold_at' => now()->subDay(),
        ]))->sortBy('id')->values();

        $parked = Sale::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'invoice_number' => null,
            'status' => SaleStatus::Held,
            'total' => 10,
        ]);

        $this->artisan('sales:backfill-invoice-numbers')->assertSuccessful();

        app(TenantContext::class)->set($owner->tenant_id);

        $this->assertSame('INV-LEGACY01', $legacy->fresh()->invoice_number);
        $this->assertSame('INV-1', $unnumbered[0]->fresh()->invoice_number);
        $this->assertSame('INV-2', $unnumbered[1]->fresh()->invoice_number);
        $this->assertNull($parked->fresh()->invoice_number);
        $this->assertSame(2, $this->sequenceFor($owner));

        // The next live checkout continues the run rather than repeating a number.
        $this->assertSame('INV-3', $this->checkout($owner, $product, $warehouse->id)['invoice_number']);

        // Re-running finds nothing left to do and does not renumber anything.
        $this->artisan('sales:backfill-invoice-numbers')->assertSuccessful();
        $this->assertSame('INV-1', $unnumbered[0]->fresh()->invoice_number);
        $this->assertSame(3, $this->sequenceFor($owner));
    }

    public function test_backfill_never_reissues_a_number_already_in_use(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);

        // A tenant whose counter is behind what its sales already carry (e.g. the
        // column was populated by a restore that did not move the counter).
        Sale::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'invoice_number' => 'INV-7',
            'status' => SaleStatus::Completed,
            'total' => 10,
            'sold_at' => now()->subDay(),
        ]);

        $orphan = Sale::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'invoice_number' => null,
            'status' => SaleStatus::Completed,
            'total' => 10,
            'sold_at' => now()->subDay(),
        ]);

        $this->artisan('sales:backfill-invoice-numbers')->assertSuccessful();

        app(TenantContext::class)->set($owner->tenant_id);

        $this->assertSame('INV-8', $orphan->fresh()->invoice_number);
        $this->assertSame(8, $this->sequenceFor($owner));
    }
}
