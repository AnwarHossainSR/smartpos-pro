<?php

namespace Database\Seeders;

use App\Domain\Sales\Data\CollectPaymentData;
use App\Domain\Sales\Data\CreateSaleData;
use App\Domain\Sales\Data\CreateSaleReturnData;
use App\Models\Account;
use App\Models\Branch;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\LoyaltyTransaction;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBatch;
use App\Models\StockSerial;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\StockTransferService;
use App\Services\Sales\QuotationService;
use App\Services\Sales\SaleReturnService;
use App\Services\Sales\SaleService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Enriches the tenant created by DemoSeeder with:
 *   - A second branch + warehouse with its own staff and sales
 *   - Sale returns on some completed sales
 *   - Manual journal entries (rent, utilities, salary)
 *   - 3 demo coupons
 *   - Expense records across categories
 *   - Loyalty point transactions for top customers
 *
 * Run after DemoSeeder: php artisan db:seed --class=RichDemoSeeder
 */
class RichDemoSeeder extends Seeder
{
    public function run(): void
    {
        $context = app(TenantContext::class);
        $returnService = app(SaleReturnService::class);

        // Find the demo owner created by DemoSeeder
        $owner = User::where('email', 'owner@smartpos.test')->firstOrFail();
        $context->set($owner->tenant_id);

        // ------------------------------------------------------------------
        // 1. Second branch
        // ------------------------------------------------------------------
        /** @var Branch $branch2 */
        $branch2 = Branch::create([
            'tenant_id' => $owner->tenant_id,
            'business_id' => $owner->business_id,
            'name' => 'SmartPOS Downtown',
            'code' => 'DT',
            'address' => '100 Main St, Downtown',
            'is_active' => true,
        ]);

        $wh2 = Warehouse::create([
            'tenant_id' => $owner->tenant_id,
            'business_id' => $owner->business_id,
            'branch_id' => $branch2->id,
            'name' => 'Downtown Stockroom',
            'code' => 'DT-WH',
            'is_active' => true,
            'is_default' => false,
        ]);

        // Staff for branch 2
        $cashier2 = User::create([
            'tenant_id' => $owner->tenant_id,
            'business_id' => $owner->business_id,
            'branch_id' => $branch2->id,
            'name' => 'Downtown Cashier',
            'email' => 'cashier2@smartpos.test',
            'password' => 'password123',
            'status' => 'active',
        ]);
        $cashier2->assignRole('cashier');

        // ------------------------------------------------------------------
        // 2. Sale returns on ~20% of sales from branch 1
        // ------------------------------------------------------------------
        $branch1Id = Branch::where('business_id', $owner->business_id)
            ->where('code', '!=', 'DT')
            ->value('id');

        $completedSales = Sale::where('branch_id', $branch1Id)
            ->where('payment_status', 'paid')
            ->with('items')
            ->limit(10)
            ->get();

        foreach ($completedSales->take(3) as $sale) {
            $firstItem = $sale->items->first();
            if (! $firstItem || $firstItem->quantity < 1) {
                continue;
            }

            try {
                $returnService->create(
                    $sale,
                    CreateSaleReturnData::fromArray([
                        'warehouse_id' => $sale->warehouse_id,
                        'refund_method' => 'cash',
                        'note' => 'Customer returned — wrong size',
                        'items' => [['sale_item_id' => $firstItem->id, 'quantity' => 1]],
                    ]),
                    $owner,
                );
            } catch (\Throwable) {
                // Skip if already returned or out of stock edge case
            }
        }

        // ------------------------------------------------------------------
        // 3. Expense categories + records
        // ------------------------------------------------------------------
        $expCats = collect(['Rent', 'Utilities', 'Salary', 'Marketing', 'Miscellaneous'])
            ->map(fn ($n) => ExpenseCategory::firstOrCreate(
                ['name' => $n, 'tenant_id' => $owner->tenant_id],
                ['slug' => Str::slug($n)],
            ));

        $branch1 = Branch::find($branch1Id);
        foreach (range(1, 12) as $i) {
            Expense::create([
                'tenant_id' => $owner->tenant_id,
                'branch_id' => $branch1->id,
                'expense_category_id' => $expCats->random()->id,
                'user_id' => $owner->id,
                'amount' => fake()->randomFloat(2, 50, 2000),
                'note' => fake()->sentence(4),
                'payment_method' => fake()->randomElement(['cash', 'bank', 'card']),
                'spent_at' => now()->subDays(fake()->numberBetween(1, 30)),
            ]);
        }

        // ------------------------------------------------------------------
        // 4. Chart of accounts + manual journals
        // ------------------------------------------------------------------
        $accounts = [
            ['name' => 'Cash',          'type' => 'asset',    'account_number' => '1000', 'normal_side' => 'debit'],
            ['name' => 'Bank',          'type' => 'asset',    'account_number' => '1001', 'normal_side' => 'debit'],
            ['name' => 'Accounts Rec.', 'type' => 'asset',    'account_number' => '1100', 'normal_side' => 'debit'],
            ['name' => 'Inventory',     'type' => 'asset',    'account_number' => '1200', 'normal_side' => 'debit'],
            ['name' => 'Accounts Pay.', 'type' => 'liability', 'account_number' => '2000', 'normal_side' => 'credit'],
            ['name' => 'Owner Equity',  'type' => 'equity',   'account_number' => '3000', 'normal_side' => 'credit'],
            ['name' => 'Sales Revenue', 'type' => 'income',   'account_number' => '4000', 'normal_side' => 'credit'],
            ['name' => 'Rent Expense',  'type' => 'expense',  'account_number' => '5000', 'normal_side' => 'debit'],
            ['name' => 'Salary Expense', 'type' => 'expense',  'account_number' => '5001', 'normal_side' => 'debit'],
            ['name' => 'Utility Exp.', 'type' => 'expense',   'account_number' => '5002', 'normal_side' => 'debit'],
        ];

        $acctMap = [];
        foreach ($accounts as $a) {
            $acct = Account::firstOrCreate(
                ['account_number' => $a['account_number'], 'tenant_id' => $owner->tenant_id],
                array_merge($a, ['tenant_id' => $owner->tenant_id, 'is_active' => true]),
            );
            $acctMap[$a['account_number']] = $acct->id;
        }

        // Monthly rent journal
        foreach (range(1, 3) as $m) {
            $amount = 2000.00;
            $entry = JournalEntry::create([
                'tenant_id' => $owner->tenant_id,
                'user_id' => $owner->id,
                'reference' => 'JNL-RENT-'.str_pad((string) $m, 3, '0', STR_PAD_LEFT),
                'entry_date' => now()->subMonths($m - 1)->startOfMonth(),
                'description' => 'Monthly rent — '.now()->subMonths($m - 1)->format('F Y'),
                'total_debit' => $amount,
                'total_credit' => $amount,
            ]);
            JournalLine::insert([
                ['id' => (string) Str::uuid(), 'tenant_id' => $owner->tenant_id, 'journal_entry_id' => $entry->id, 'account_id' => $acctMap['5000'], 'debit' => $amount, 'credit' => 0, 'description' => 'Rent expense'],
                ['id' => (string) Str::uuid(), 'tenant_id' => $owner->tenant_id, 'journal_entry_id' => $entry->id, 'account_id' => $acctMap['1001'], 'debit' => 0, 'credit' => $amount, 'description' => 'Paid from bank'],
            ]);
        }

        // Salary journal
        $salary = 5000.00;
        $salEntry = JournalEntry::create([
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'reference' => 'JNL-SAL-001',
            'entry_date' => now()->subDays(15),
            'description' => 'Staff salary — '.now()->subDays(15)->format('M Y'),
            'total_debit' => $salary,
            'total_credit' => $salary,
        ]);
        JournalLine::insert([
            ['id' => (string) Str::uuid(), 'tenant_id' => $owner->tenant_id, 'journal_entry_id' => $salEntry->id, 'account_id' => $acctMap['5001'], 'debit' => $salary, 'credit' => 0, 'description' => 'Salary'],
            ['id' => (string) Str::uuid(), 'tenant_id' => $owner->tenant_id, 'journal_entry_id' => $salEntry->id, 'account_id' => $acctMap['1000'], 'debit' => 0, 'credit' => $salary, 'description' => 'Cash payment'],
        ]);

        // ------------------------------------------------------------------
        // 5. Demo coupons
        // ------------------------------------------------------------------
        $coupons = [
            ['code' => 'WELCOME10', 'type' => 'percentage', 'value' => 10,   'min_purchase' => 0,   'usage_limit' => 100, 'is_active' => true],
            ['code' => 'SAVE20',    'type' => 'fixed',      'value' => 20,   'min_purchase' => 100, 'usage_limit' => 50,  'is_active' => true],
            ['code' => 'SUMMER50',  'type' => 'percentage', 'value' => 50,   'min_purchase' => 200, 'usage_limit' => 20,  'is_active' => false, 'expires_at' => now()->subDay()],
        ];

        foreach ($coupons as $c) {
            Coupon::firstOrCreate(
                ['code' => $c['code'], 'tenant_id' => $owner->tenant_id],
                array_merge($c, ['tenant_id' => $owner->tenant_id]),
            );
        }

        // ------------------------------------------------------------------
        // 6. Loyalty points for customers with purchases
        // ------------------------------------------------------------------
        if (class_exists(LoyaltyTransaction::class)) {
            $customersWithSales = Sale::where('branch_id', $branch1Id)
                ->whereNotNull('customer_id')
                ->distinct()
                ->limit(5)
                ->pluck('customer_id');

            foreach ($customersWithSales as $customerId) {
                $pts = fake()->numberBetween(100, 500);
                LoyaltyTransaction::create([
                    'tenant_id' => $owner->tenant_id,
                    'customer_id' => $customerId,
                    'user_id' => $owner->id,
                    'type' => 'earn',
                    'points' => $pts,
                    'balance_after' => $pts,
                    'note' => 'Seeded opening loyalty balance',
                ]);
            }
        }

        // ------------------------------------------------------------------
        // 7. Stock transfer from branch 1 to the new Downtown branch
        // ------------------------------------------------------------------
        $transferProduct = Product::inRandomOrder()->first();
        $wh1 = Warehouse::where('business_id', $owner->business_id)->where('id', '!=', $wh2->id)->first();

        if ($transferProduct && $wh1) {
            $transfers = app(StockTransferService::class);
            $transfer = $transfers->request([
                'from_warehouse_id' => $wh1->id,
                'to_warehouse_id' => $wh2->id,
                'note' => 'Restocking the Downtown branch',
                'items' => [['product_id' => $transferProduct->id, 'quantity' => 10]],
            ], $owner);
            $transfer = $transfers->approve($transfer, $owner);
            $transfers->receive($transfer, [
                'items' => $transfer->items->map(fn ($item) => [
                    'transfer_item_id' => $item->id,
                    'quantity_received' => (float) $item->quantity_requested,
                ])->all(),
            ], $owner);
        }

        // ------------------------------------------------------------------
        // 8. Product variants, a stock batch and a serial number
        // ------------------------------------------------------------------
        $variantProduct = Product::inRandomOrder()->first();

        if ($variantProduct) {
            foreach (['Small', 'Medium', 'Large'] as $size) {
                $variantProduct->variants()->create([
                    'name' => "{$variantProduct->name} - {$size}",
                    'sku' => $variantProduct->sku.'-'.strtoupper(substr($size, 0, 1)),
                    'attributes' => ['size' => $size],
                    'selling_price' => $variantProduct->selling_price,
                ]);
            }
        }

        $batchProduct = Product::inRandomOrder()->first();
        if ($batchProduct && $wh1) {
            StockBatch::create([
                'product_id' => $batchProduct->id,
                'warehouse_id' => $wh1->id,
                'batch_number' => 'BATCH-'.now()->format('Ym').'-001',
                'quantity' => 40,
                'unit_cost' => $batchProduct->purchase_price,
                'manufactured_at' => now()->subMonths(2),
                'expires_at' => now()->addMonths(4),
            ]);
        }

        $serialProduct = Product::inRandomOrder()->first();
        if ($serialProduct && $wh1) {
            foreach (range(1, 3) as $n) {
                StockSerial::create([
                    'product_id' => $serialProduct->id,
                    'warehouse_id' => $wh1->id,
                    'serial_number' => 'SN-'.strtoupper(Str::random(8)),
                    'status' => 'in_stock',
                ]);
            }
        }

        // ------------------------------------------------------------------
        // 9. A quotation converted into a sale
        // ------------------------------------------------------------------
        $quoteProduct = Product::inRandomOrder()->first();
        if ($quoteProduct && $wh1) {
            $quotations = app(QuotationService::class);
            $quotation = $quotations->create([
                'branch_id' => $branch1Id,
                'note' => 'Bulk order quote for a walk-in customer',
                'items' => [['product_id' => $quoteProduct->id, 'quantity' => 5]],
            ], $owner);

            $quotations->convertToSale($quotation, [
                'warehouse_id' => $wh1->id,
                'payments' => [['method' => 'cash', 'amount' => (float) $quotation->total]],
            ], $owner);
        }

        // ------------------------------------------------------------------
        // 10. A partially-settled credit sale, to showcase collecting the rest
        // ------------------------------------------------------------------
        $creditCustomer = Customer::inRandomOrder()->first();
        $creditProduct = Product::inRandomOrder()->first();

        if ($creditCustomer && $creditProduct && $wh1) {
            $sales = app(SaleService::class);
            $creditSale = $sales->create(CreateSaleData::fromArray([
                'branch_id' => $branch1Id,
                'warehouse_id' => $wh1->id,
                'customer_id' => $creditCustomer->id,
                'items' => [['product_id' => $creditProduct->id, 'quantity' => 4]],
                'payments' => [['method' => 'cash', 'amount' => round((float) $creditProduct->selling_price * 4 * 0.4, 2)]],
            ]), $owner);

            // Customer pays down part of the remaining balance, leaving it "partial".
            if ((float) $creditSale->due_amount > 0) {
                $sales->collectPayment($creditSale, CollectPaymentData::fromArray([
                    'method' => 'bank',
                    'amount' => round((float) $creditSale->due_amount / 2, 2),
                ]));
            }
        }

        $this->command?->info('Rich demo data seeded: branch 2, returns, journals, expenses, coupons, loyalty,');
        $this->command?->info('stock transfer, variants/batch/serial, a converted quotation, and a partially-paid credit sale.');
        $this->command?->info('Branch 2 cashier: cashier2@smartpos.test / password123');
    }
}
