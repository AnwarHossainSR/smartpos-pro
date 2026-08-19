<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class FinancialStatementTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function seedFinancialData(): array
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $cash = Account::create(['name' => 'Cash', 'type' => 'cash', 'account_number' => '1000', 'balance' => 0]);
        $salesRevenue = Account::create(['name' => 'Sales Revenue', 'type' => 'income', 'account_number' => '4000', 'balance' => 0]);
        $cogs = Account::create(['name' => 'Cost of Goods Sold', 'type' => 'expense', 'account_number' => '5000', 'balance' => 0]);
        $rent = Account::create(['name' => 'Rent Expense', 'type' => 'expense', 'account_number' => '6000', 'balance' => 0]);
        $inventory = Account::create(['name' => 'Inventory', 'type' => 'asset', 'account_number' => '1200', 'balance' => 0]);
        $accountsPayable = Account::create(['name' => 'Accounts Payable', 'type' => 'liability', 'account_number' => '2000', 'balance' => 0]);
        $equity = Account::create(['name' => 'Owner Equity', 'type' => 'equity', 'account_number' => '3000', 'balance' => 0]);

        app(TenantContext::class)->set($owner->tenant_id);

        JournalEntry::create([
            'reference' => 'JE-001',
            'entry_date' => '2025-01-15',
            'description' => 'Initial investment',
            'total_debit' => 10000,
            'total_credit' => 10000,
        ])->lines()->createMany([
            ['account_id' => $cash->id, 'type' => 'debit', 'amount' => 10000, 'debit' => 10000, 'credit' => 0],
            ['account_id' => $equity->id, 'type' => 'credit', 'amount' => 10000, 'debit' => 0, 'credit' => 10000],
        ]);

        JournalEntry::create([
            'reference' => 'JE-002',
            'entry_date' => '2025-01-20',
            'description' => 'Cash sale',
            'total_debit' => 500,
            'total_credit' => 500,
        ])->lines()->createMany([
            ['account_id' => $cash->id, 'type' => 'debit', 'amount' => 500, 'debit' => 500, 'credit' => 0],
            ['account_id' => $salesRevenue->id, 'type' => 'credit', 'amount' => 500, 'debit' => 0, 'credit' => 500],
        ]);

        JournalEntry::create([
            'reference' => 'JE-003',
            'entry_date' => '2025-01-20',
            'description' => 'COGS for sale',
            'total_debit' => 200,
            'total_credit' => 200,
        ])->lines()->createMany([
            ['account_id' => $cogs->id, 'type' => 'debit', 'amount' => 200, 'debit' => 200, 'credit' => 0],
            ['account_id' => $inventory->id, 'type' => 'credit', 'amount' => 200, 'debit' => 0, 'credit' => 200],
        ]);

        JournalEntry::create([
            'reference' => 'JE-004',
            'entry_date' => '2025-01-25',
            'description' => 'Rent payment',
            'total_debit' => 150,
            'total_credit' => 150,
        ])->lines()->createMany([
            ['account_id' => $rent->id, 'type' => 'debit', 'amount' => 150, 'debit' => 150, 'credit' => 0],
            ['account_id' => $cash->id, 'type' => 'credit', 'amount' => 150, 'debit' => 0, 'credit' => 150],
        ]);

        JournalEntry::create([
            'reference' => 'JE-005',
            'entry_date' => '2025-02-01',
            'description' => 'Purchase on credit',
            'total_debit' => 300,
            'total_credit' => 300,
        ])->lines()->createMany([
            ['account_id' => $inventory->id, 'type' => 'debit', 'amount' => 300, 'debit' => 300, 'credit' => 0],
            ['account_id' => $accountsPayable->id, 'type' => 'credit', 'amount' => 300, 'debit' => 0, 'credit' => 300],
        ]);

        return compact('owner', 'cash', 'salesRevenue', 'cogs', 'rent', 'inventory', 'accountsPayable', 'equity');
    }

    public function test_profit_loss_report(): void
    {
        $this->seedFinancialData();

        $response = $this->getJson('/api/v1/reports/profit-loss?from=2025-01-01&to=2025-01-31')
            ->assertOk()
            ->json('data');

        $this->assertSame('2025-01-01', $response['from']);
        $this->assertSame('2025-01-31', $response['to']);
        $this->assertEqualsWithDelta(500, $response['revenue_total'], 0.01);
        $this->assertEqualsWithDelta(200, $response['cogs_total'], 0.01);
        $this->assertEqualsWithDelta(300, $response['gross_profit'], 0.01);
        $this->assertEqualsWithDelta(150, $response['expenses_total'], 0.01);
        $this->assertEqualsWithDelta(150, $response['net_profit'], 0.01);
    }

    public function test_profit_loss_with_no_data(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/profit-loss?from=2025-01-01&to=2025-01-31')
            ->assertOk()
            ->json('data');

        $this->assertEqualsWithDelta(0, $response['revenue_total'], 0.01);
        $this->assertEqualsWithDelta(0, $response['cogs_total'], 0.01);
        $this->assertEqualsWithDelta(0, $response['gross_profit'], 0.01);
        $this->assertEqualsWithDelta(0, $response['expenses_total'], 0.01);
        $this->assertEqualsWithDelta(0, $response['net_profit'], 0.01);
    }

    public function test_balance_sheet(): void
    {
        $this->seedFinancialData();

        $response = $this->getJson('/api/v1/reports/balance-sheet?as_of=2025-02-01')
            ->assertOk()
            ->json('data');

        $this->assertSame('2025-02-01', $response['as_of']);
        $this->assertEqualsWithDelta(10450, $response['assets_total'], 0.01);
        $this->assertEqualsWithDelta(300, $response['liabilities_total'], 0.01);
        $this->assertEqualsWithDelta(10000, $response['equity_total'], 0.01);
        $this->assertEqualsWithDelta(150, $response['retained_earnings'], 0.01);
        $this->assertEqualsWithDelta(10450, $response['total_liabilities_equity'], 0.01);
    }

    public function test_balance_sheet_assets_equal_liabilities_plus_equity(): void
    {
        $this->seedFinancialData();

        $response = $this->getJson('/api/v1/reports/balance-sheet?as_of=2025-02-01')
            ->assertOk()
            ->json('data');

        $this->assertEqualsWithDelta(
            $response['assets_total'],
            $response['total_liabilities_equity'],
            0.01,
        );
    }

    public function test_balance_sheet_requires_as_of_date(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/reports/balance-sheet')
            ->assertStatus(422);
    }

    public function test_profit_loss_requires_date_range(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/reports/profit-loss?from=2025-01-31&to=2025-01-01')
            ->assertStatus(422);
    }
}
