<?php

namespace Tests\Feature\Api;

use App\Models\CashRegister;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

/**
 * The end-of-day close. A Z-report is the document a shift is signed off on, so
 * every group on it has to sum back to the same day's invoices — a payment grid
 * that doesn't reconcile against net sales is worse than no report at all.
 */
class ZReportTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedProduct(string $warehouseId, float $price, ?string $taxGroupId = null): Product
    {
        $product = Product::create([
            'name' => 'Widget', 'slug' => 'widget-'.uniqid(), 'sku' => 'SKU-'.uniqid(),
            'purchase_price' => $price / 2, 'selling_price' => $price,
            'tax_group_id' => $taxGroupId, 'min_stock_level' => 0,
        ]);

        app(StockLedgerService::class)->addOpeningStock($product->id, $warehouseId, 100, $price / 2);

        return $product;
    }

    /** Pin a sale to a known minute so first/last invoice ordering is deterministic. */
    private function soldAt(string $saleId, string $time): void
    {
        Sale::whereKey($saleId)->update(['sold_at' => Carbon::today()->setTimeFromTimeString($time)]);
    }

    public function test_a_trading_day_reconciles_across_every_group(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $group = $this->taxGroupWithComponents(['CGST' => 5, 'SGST' => 5]);
        $product = $this->seedProduct($warehouse->id, 100, $group->id);

        $base = ['branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id];

        // A — split tender: 2 units @ 100 = 200 net + 10% tax = 220, paid 120 cash + 100 card.
        $a = $this->postJson('/api/v1/sales', $base + [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 120], ['method' => 'card', 'amount' => 100]],
        ])->assertCreated()->json('data');

        // B — invoice-level discount: 100 net + 10 tax - 20 discount = 90.
        $b = $this->postJson('/api/v1/sales', $base + [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'discount_amount' => 20,
            'payments' => [['method' => 'cash', 'amount' => 90]],
        ])->assertCreated()->json('data');

        // C — rung up in error and voided; must fall out of every sales group.
        $c = $this->postJson('/api/v1/sales', $base + [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 110]],
        ])->assertCreated()->json('data');

        // D — sold, then fully returned; the sale itself still counts, the refund is its own line.
        $d = $this->postJson('/api/v1/sales', $base + [
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 110]],
        ])->assertCreated()->json('data');

        $this->soldAt($a['id'], '09:00');
        $this->soldAt($b['id'], '10:00');
        $this->soldAt($c['id'], '11:00');
        $this->soldAt($d['id'], '12:00');

        $this->postJson("/api/v1/sales/{$c['id']}/void", ['reason' => 'Wrong item'])->assertOk();

        $return = $this->postJson("/api/v1/sales/{$d['id']}/returns", [
            'items' => [['sale_item_id' => $d['items'][0]['id'], 'quantity' => 1]],
        ])->assertCreated()->json('data');

        // Drawer: opened with 100, 50 paid in, counted 145 — a 5 short.
        $register = CashRegister::create(['branch_id' => $owner->branch_id, 'name' => 'Front Desk']);
        $session = $this->postJson("/api/v1/cash-registers/{$register->id}/open", ['opening_cash' => 100])
            ->assertCreated()->json('data');
        $this->postJson("/api/v1/cash-register-sessions/{$session['id']}/movement", ['type' => 'in', 'amount' => 50])->assertOk();
        $this->postJson("/api/v1/cash-register-sessions/{$session['id']}/close", ['actual_cash' => 145])->assertOk();

        $report = $this->getJson('/api/v1/reports/z-report?date='.Carbon::today()->toDateString())
            ->assertOk()->json('data');

        // ---- headline totals: only A, B and D are completed sales ----
        $this->assertSame(3, $report['totals']['transactions']);
        $this->assertEquals(400.0, $report['totals']['gross_sales']);   // 200 + 100 + 100
        $this->assertEquals(40.0, $report['totals']['tax']);            // 20 + 10 + 10
        $this->assertEquals(20.0, $report['totals']['discounts']);      // B's invoice discount
        $this->assertEquals(420.0, $report['totals']['net_sales']);     // 220 + 90 + 110
        $this->assertEquals(140.0, $report['totals']['average_basket']);
        $this->assertEquals(420.0, $report['totals']['amount_paid']);
        $this->assertEquals(0.0, $report['totals']['amount_due']);

        // The day reconciles: gross - discounts + tax + shipping == net sales.
        $this->assertEquals(
            $report['totals']['net_sales'],
            round($report['totals']['gross_sales'] - $report['totals']['discounts']
                + $report['totals']['tax'] + $report['totals']['shipping'], 2),
        );

        // ---- payment methods: the voided sale's cash is gone, the rest sums to net sales ----
        $methods = collect($report['payment_methods'])->keyBy('method');
        $this->assertEquals(320.0, $methods['cash']['amount']);         // 120 + 90 + 110
        $this->assertSame(3, $methods['cash']['count']);
        $this->assertEquals(100.0, $methods['card']['amount']);
        $this->assertSame(1, $methods['card']['count']);
        $this->assertEquals($report['totals']['net_sales'], round(collect($report['payment_methods'])->sum('amount'), 2));

        // ---- tax by component, read from the sale_item_taxes snapshots ----
        $components = collect($report['taxes']['components'])->keyBy('name');
        $this->assertEqualsCanonicalizing(['CGST', 'SGST'], $components->keys()->all());
        $this->assertEquals(20.0, $components['CGST']['amount']);
        $this->assertEquals(20.0, $components['SGST']['amount']);
        $this->assertEquals(400.0, $components['CGST']['base_amount']);
        $this->assertEquals($report['totals']['tax'], $report['taxes']['total']);

        // ---- discounts, invoice range, corrections ----
        $this->assertEquals(20.0, $report['discounts']['sale']);
        $this->assertEquals(0.0, $report['discounts']['line']);
        $this->assertEquals(0.0, $report['discounts']['coupon']);
        $this->assertSame($a['invoice_number'], $report['invoices']['first']);
        $this->assertSame($d['invoice_number'], $report['invoices']['last']);
        $this->assertSame(1, $report['voids']['count']);
        $this->assertEquals(110.0, $report['voids']['amount']);
        $this->assertSame(1, $report['returns']['count']);
        $this->assertEquals((float) $return['total'], $report['returns']['amount']);

        // ---- cash reconciliation straight off the session's own columns ----
        $this->assertSame(1, $report['cash_sessions']['count']);
        $this->assertEquals(100.0, $report['cash_sessions']['opening_cash']);
        $this->assertEquals(150.0, $report['cash_sessions']['expected_cash']);
        $this->assertEquals(145.0, $report['cash_sessions']['actual_cash']);
        $this->assertEquals(-5.0, $report['cash_sessions']['difference']);
        $this->assertCount(1, $report['cash_sessions']['sessions']);
    }

    public function test_a_day_with_no_trading_returns_zeros_rather_than_an_error(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $report = $this->getJson('/api/v1/reports/z-report?date='.Carbon::today()->subMonth()->toDateString())
            ->assertOk()->json('data');

        $this->assertSame(0, $report['totals']['transactions']);
        $this->assertEquals(0.0, $report['totals']['net_sales']);
        $this->assertEquals(0.0, $report['totals']['average_basket']);
        $this->assertSame([], $report['payment_methods']);
        $this->assertSame([], $report['taxes']['components']);
        $this->assertEquals(0.0, $report['taxes']['total']);
        $this->assertNull($report['invoices']['first']);
        $this->assertNull($report['invoices']['last']);
        $this->assertSame(0, $report['cash_sessions']['count']);
        $this->assertNull($report['cash_sessions']['difference']);
        $this->assertSame(0, $report['voids']['count']);
        $this->assertSame(0, $report['returns']['count']);
    }

    public function test_the_branch_filter_narrows_the_day_to_one_branch(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);

        $product = $this->seedProduct($warehouse->id, 50);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated();

        $other = $owner->branch->replicate()->fill(['name' => 'Second branch', 'code' => 'B2', 'is_default' => false]);
        $other->save();

        $today = Carbon::today()->toDateString();

        $mine = $this->getJson("/api/v1/reports/z-report?date={$today}&branch_id={$owner->branch_id}")
            ->assertOk()->json('data');
        $this->assertSame(1, $mine['totals']['transactions']);
        $this->assertSame($owner->branch_id, $mine['branch']['id']);

        $theirs = $this->getJson("/api/v1/reports/z-report?date={$today}&branch_id={$other->id}")
            ->assertOk()->json('data');
        $this->assertSame(0, $theirs['totals']['transactions']);
        $this->assertEquals(0.0, $theirs['totals']['net_sales']);
    }

    public function test_the_report_never_sees_another_tenants_day(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 100);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 100]],
        ])->assertCreated();

        // A second business trades the same day, five times as hard.
        $intruder = $this->createTenant('rival@example.test');
        $rivalWarehouse = $this->warehouseFor($intruder);
        Sanctum::actingAs($intruder);
        $rivalProduct = $this->seedProduct($rivalWarehouse->id, 100);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $intruder->branch_id, 'warehouse_id' => $rivalWarehouse->id,
            'items' => [['product_id' => $rivalProduct->id, 'quantity' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 500]],
        ])->assertCreated();

        // Back to the first owner: their close must be untouched by the neighbour's.
        app(TenantContext::class)->set($owner->tenant_id);
        Sanctum::actingAs($owner->fresh());

        $report = $this->getJson('/api/v1/reports/z-report?date='.Carbon::today()->toDateString())
            ->assertOk()->json('data');

        $this->assertSame(1, $report['totals']['transactions']);
        $this->assertEquals(100.0, $report['totals']['net_sales']);
        $this->assertEquals(100.0, collect($report['payment_methods'])->sum('amount'));
    }

    public function test_the_pdf_streams_inline_and_downloads_on_request(): void
    {
        $owner = $this->createTenant();
        $warehouse = $this->warehouseFor($owner);
        Sanctum::actingAs($owner);
        $product = $this->seedProduct($warehouse->id, 100, $this->taxGroup(10)->id);

        $this->postJson('/api/v1/sales', [
            'branch_id' => $owner->branch_id, 'warehouse_id' => $warehouse->id,
            'items' => [['product_id' => $product->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 110]],
        ])->assertCreated();

        $today = Carbon::today()->toDateString();

        $this->get("/api/v1/reports/z-report/pdf?date={$today}")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $download = $this->get("/api/v1/reports/z-report/pdf?date={$today}&download=1")->assertOk();
        $this->assertStringContainsString('attachment', $download->headers->get('Content-Disposition'));

        // An empty day must still render a document, not blow up on null totals.
        $this->get('/api/v1/reports/z-report/pdf?date='.Carbon::today()->subYear()->toDateString())
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_reports_view_permission_is_required(): void
    {
        $owner = $this->createTenant();
        $cashier = User::create([
            'business_id' => $owner->business_id, 'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        Sanctum::actingAs($cashier->fresh('roles'));

        $this->getJson('/api/v1/reports/z-report')->assertForbidden();
    }
}
