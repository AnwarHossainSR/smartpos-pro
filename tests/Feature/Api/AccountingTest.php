<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_balanced_journal_posts_and_updates_account_balances(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $cash = Account::create(['name' => 'Cash', 'type' => 'cash', 'balance' => 0]);
        $sales = Account::create(['name' => 'Sales Income', 'type' => 'income', 'balance' => 0]);

        $this->postJson('/api/v1/journals', [
            'description' => 'Cash sale',
            'lines' => [
                ['account_id' => $cash->id, 'type' => 'debit', 'amount' => 100],
                ['account_id' => $sales->id, 'type' => 'credit', 'amount' => 100],
            ],
        ])->assertCreated()->assertJsonPath('data.total_debit', 100);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertEqualsWithDelta(100, (float) $cash->fresh()->balance, 0.001);   // asset up on debit
        $this->assertEqualsWithDelta(100, (float) $sales->fresh()->balance, 0.001);  // income up on credit
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $cash = Account::create(['name' => 'Cash', 'type' => 'cash', 'balance' => 0]);
        $sales = Account::create(['name' => 'Sales', 'type' => 'income', 'balance' => 0]);

        $this->postJson('/api/v1/journals', [
            'lines' => [
                ['account_id' => $cash->id, 'type' => 'debit', 'amount' => 100],
                ['account_id' => $sales->id, 'type' => 'credit', 'amount' => 50],
            ],
        ])->assertStatus(422);

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_trial_balance_splits_accounts_by_normal_side_and_the_columns_agree(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $cash = Account::create(['name' => 'Cash', 'type' => 'cash', 'account_number' => '1000', 'balance' => 0]);
        $sales = Account::create(['name' => 'Sales Income', 'type' => 'income', 'account_number' => '4000', 'balance' => 0]);

        $this->postJson('/api/v1/journals', [
            'description' => 'Cash sale',
            'lines' => [
                ['account_id' => $cash->id, 'type' => 'debit', 'amount' => 250],
                ['account_id' => $sales->id, 'type' => 'credit', 'amount' => 250],
            ],
        ])->assertCreated();

        $response = $this->getJson('/api/v1/journals/trial-balance')->assertOk();

        $rows = collect($response->json('data.accounts'))->keyBy('account_id');

        // cash is debit-normal, income is credit-normal — each balance lands in one column.
        $this->assertSame('debit', $rows[$cash->id]['normal_side']);
        $this->assertEqualsWithDelta(250, $rows[$cash->id]['debit'], 0.001);
        $this->assertEqualsWithDelta(0, $rows[$cash->id]['credit'], 0.001);
        $this->assertSame('1000', $rows[$cash->id]['account_number']);

        $this->assertSame('credit', $rows[$sales->id]['normal_side']);
        $this->assertEqualsWithDelta(250, $rows[$sales->id]['credit'], 0.001);
        $this->assertEqualsWithDelta(0, $rows[$sales->id]['debit'], 0.001);

        // The point of the report: a balanced ledger has equal column totals.
        $response->assertJsonPath('data.total_debit', 250)
            ->assertJsonPath('data.total_credit', 250);
    }

    public function test_account_resource_exposes_the_side_its_balance_moves_on(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        Account::create(['name' => 'Bank', 'type' => 'bank', 'balance' => 0]);
        Account::create(['name' => 'Loan', 'type' => 'liability', 'balance' => 0]);

        $sides = collect($this->getJson('/api/v1/accounts')->assertOk()->json('data'))
            ->pluck('normal_side', 'name');

        $this->assertSame('debit', $sides['Bank']);
        $this->assertSame('credit', $sides['Loan']);
    }
}
