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
}
