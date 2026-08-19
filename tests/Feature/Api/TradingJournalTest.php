<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use App\Models\Warehouse;
use App\Services\Accounting\TradingJournalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class TradingJournalTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedAccounts(string $tenantId): void
    {
        $accounts = [
            ['account_number' => '1000', 'name' => 'Cash', 'type' => 'cash'],
            ['account_number' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            ['account_number' => '2000', 'name' => 'Accounts Payable', 'type' => 'liability'],
            ['account_number' => '2100', 'name' => 'Tax Payable', 'type' => 'liability'],
            ['account_number' => '4000', 'name' => 'Sales Revenue', 'type' => 'income'],
            ['account_number' => '4100', 'name' => 'Sales Returns', 'type' => 'income'],
            ['account_number' => '6000', 'name' => 'Operating Expenses', 'type' => 'expense'],
            ['account_number' => '6100', 'name' => 'Rent Expense', 'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            Account::create([
                'tenant_id' => $tenantId,
                'account_number' => $account['account_number'],
                'name' => $account['name'],
                'type' => $account['type'],
                'balance' => 0,
                'is_active' => true,
            ]);
        }
    }

    public function test_sale_creates_balanced_journal_entry(): void
    {
        $owner = $this->createTenant();
        $this->seedAccounts($owner->tenant_id);
        Sanctum::actingAs($owner);

        $business = Business::where('tenant_id', $owner->tenant_id)->first();
        $business->update(['auto_post_journals' => true]);

        $warehouse = Warehouse::where('business_id', $business->id)->first();
        $customer = Customer::create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Test Customer',
            'phone' => '01700000001',
            'is_active' => true,
        ]);

        $product = Product::create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Test Product',
            'slug' => 'test-product',
            'sku' => 'TEST-001',
            'purchase_price' => 100,
            'selling_price' => 150,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'tenant_id' => $owner->tenant_id,
            'business_id' => $business->id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'user_id' => $owner->id,
            'invoice_number' => 'INV-TEST-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 150,
            'discount_amount' => 0,
            'coupon_discount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total' => 150,
            'paid_amount' => 150,
            'change_amount' => 0,
            'due_amount' => 0,
            'sold_at' => now(),
        ]);

        SalePayment::create([
            'tenant_id' => $owner->tenant_id,
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 150,
            'paid_at' => now(),
        ]);

        app(TradingJournalService::class)->postSale($sale);

        $entries = JournalEntry::where('tenant_id', $owner->tenant_id)
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->get();

        $this->assertCount(1, $entries);

        $entry = $entries->first();
        $this->assertEqualsWithDelta(150, (float) $entry->total_debit, 0.01);
        $this->assertEqualsWithDelta(150, (float) $entry->total_credit, 0.01);

        $cashAccount = Account::where('tenant_id', $owner->tenant_id)
            ->where('account_number', '1000')->first();
        $salesAccount = Account::where('tenant_id', $owner->tenant_id)
            ->where('account_number', '4000')->first();

        $this->assertEqualsWithDelta(150, (float) $cashAccount->fresh()->balance, 0.01);
        $this->assertEqualsWithDelta(150, (float) $salesAccount->fresh()->balance, 0.01);
    }

    public function test_sale_with_tax_creates_journal_with_tax_payable(): void
    {
        $owner = $this->createTenant();
        $this->seedAccounts($owner->tenant_id);
        Sanctum::actingAs($owner);

        $business = Business::where('tenant_id', $owner->tenant_id)->first();
        $business->update(['auto_post_journals' => true]);

        $warehouse = Warehouse::where('business_id', $business->id)->first();

        $product = Product::create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'Taxed Product',
            'slug' => 'taxed-product',
            'sku' => 'TAX-001',
            'purchase_price' => 100,
            'selling_price' => 115,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'tenant_id' => $owner->tenant_id,
            'business_id' => $business->id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'invoice_number' => 'INV-TAX-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 100,
            'discount_amount' => 0,
            'coupon_discount' => 0,
            'tax_amount' => 15,
            'shipping_amount' => 0,
            'total' => 115,
            'paid_amount' => 115,
            'change_amount' => 0,
            'due_amount' => 0,
            'sold_at' => now(),
        ]);

        SalePayment::create([
            'tenant_id' => $owner->tenant_id,
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 115,
            'paid_at' => now(),
        ]);

        app(TradingJournalService::class)->postSale($sale);

        $entry = JournalEntry::where('tenant_id', $owner->tenant_id)
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->first();

        $this->assertNotNull($entry);
        $this->assertEqualsWithDelta(115, (float) $entry->total_debit, 0.01);
        $this->assertEqualsWithDelta(115, (float) $entry->total_credit, 0.01);

        $taxPayable = Account::where('tenant_id', $owner->tenant_id)
            ->where('account_number', '2100')->first();
        $this->assertEqualsWithDelta(15, (float) $taxPayable->fresh()->balance, 0.01);
    }

    public function test_expense_creates_journal_entry(): void
    {
        $owner = $this->createTenant();
        $this->seedAccounts($owner->tenant_id);
        Sanctum::actingAs($owner);

        $business = Business::where('tenant_id', $owner->tenant_id)->first();
        $business->update(['auto_post_journals' => true]);

        $expense = Expense::create([
            'tenant_id' => $owner->tenant_id,
            'branch_id' => $owner->branch_id,
            'user_id' => $owner->id,
            'reference' => 'EXP-TEST-001',
            'amount' => 500,
            'payment_method' => 'cash',
            'status' => 'approved',
            'note' => 'Test expense',
            'spent_at' => now()->toDateString(),
        ]);

        app(TradingJournalService::class)->postExpense($expense);

        $entries = JournalEntry::where('tenant_id', $owner->tenant_id)
            ->where('source_type', Expense::class)
            ->where('source_id', $expense->id)
            ->get();

        $this->assertCount(1, $entries);

        $entry = $entries->first();
        $this->assertEqualsWithDelta(500, (float) $entry->total_debit, 0.01);
        $this->assertEqualsWithDelta(500, (float) $entry->total_credit, 0.01);
    }

    public function test_opt_out_tenant_produces_no_entries(): void
    {
        $owner = $this->createTenant();
        $this->seedAccounts($owner->tenant_id);
        Sanctum::actingAs($owner);

        $business = Business::where('tenant_id', $owner->tenant_id)->first();
        $business->update(['auto_post_journals' => false]);

        $warehouse = Warehouse::where('business_id', $business->id)->first();

        $product = Product::create([
            'tenant_id' => $owner->tenant_id,
            'name' => 'No Journal Product',
            'slug' => 'no-journal-product',
            'sku' => 'NOJ-001',
            'purchase_price' => 100,
            'selling_price' => 150,
            'track_inventory' => true,
            'is_active' => true,
        ]);

        $sale = Sale::create([
            'tenant_id' => $owner->tenant_id,
            'business_id' => $business->id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'invoice_number' => 'INV-NOJ-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 150,
            'discount_amount' => 0,
            'coupon_discount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total' => 150,
            'paid_amount' => 150,
            'change_amount' => 0,
            'due_amount' => 0,
            'sold_at' => now(),
        ]);

        SalePayment::create([
            'tenant_id' => $owner->tenant_id,
            'sale_id' => $sale->id,
            'method' => 'cash',
            'amount' => 150,
            'paid_at' => now(),
        ]);

        app(TradingJournalService::class)->postSale($sale);

        $entries = JournalEntry::where('tenant_id', $owner->tenant_id)
            ->where('source_type', Sale::class)
            ->where('source_id', $sale->id)
            ->get();

        $this->assertCount(0, $entries);
    }

    public function test_sale_return_creates_reversal_journal(): void
    {
        $owner = $this->createTenant();
        $this->seedAccounts($owner->tenant_id);
        Sanctum::actingAs($owner);

        $business = Business::where('tenant_id', $owner->tenant_id)->first();
        $business->update(['auto_post_journals' => true]);

        $warehouse = Warehouse::where('business_id', $business->id)->first();

        $sale = Sale::create([
            'tenant_id' => $owner->tenant_id,
            'business_id' => $business->id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'invoice_number' => 'INV-RET-001',
            'status' => 'completed',
            'payment_status' => 'paid',
            'subtotal' => 200,
            'discount_amount' => 0,
            'coupon_discount' => 0,
            'tax_amount' => 0,
            'shipping_amount' => 0,
            'total' => 200,
            'paid_amount' => 200,
            'change_amount' => 0,
            'due_amount' => 0,
            'sold_at' => now(),
        ]);

        $return = SaleReturn::create([
            'tenant_id' => $owner->tenant_id,
            'sale_id' => $sale->id,
            'branch_id' => $owner->branch_id,
            'warehouse_id' => $warehouse->id,
            'user_id' => $owner->id,
            'reference_number' => 'RET-TEST-001',
            'subtotal' => 200,
            'tax_amount' => 0,
            'total' => 200,
            'refund_method' => 'cash',
            'returned_at' => now(),
        ]);

        app(TradingJournalService::class)->postSaleReturn($return);

        $entries = JournalEntry::where('tenant_id', $owner->tenant_id)
            ->where('source_type', SaleReturn::class)
            ->where('source_id', $return->id)
            ->get();

        $this->assertCount(1, $entries);

        $entry = $entries->first();
        $this->assertEqualsWithDelta(200, (float) $entry->total_debit, 0.01);
        $this->assertEqualsWithDelta(200, (float) $entry->total_credit, 0.01);
    }
}
