<?php

namespace Tests\Feature\Api;

use App\Models\CashRegister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class CashRegisterTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_open_move_and_close_reconciles_expected_vs_actual(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $register = CashRegister::create(['branch_id' => $owner->branch_id, 'name' => 'Front Desk']);

        // Opening a session creates a model, so the resource responds 201.
        $session = $this->postJson("/api/v1/cash-registers/{$register->id}/open", ['opening_cash' => 100])
            ->assertCreated()->json('data');

        $this->postJson("/api/v1/cash-register-sessions/{$session['id']}/movement", ['type' => 'in', 'amount' => 50, 'reason' => 'Float top-up'])->assertOk();
        $this->postJson("/api/v1/cash-register-sessions/{$session['id']}/movement", ['type' => 'out', 'amount' => 20, 'reason' => 'Petty cash'])->assertOk();

        // Expected = 100 + 50 - 20 = 130; actual counted 125 => difference -5.
        $this->postJson("/api/v1/cash-register-sessions/{$session['id']}/close", ['actual_cash' => 125])
            ->assertOk()
            ->assertJsonPath('data.expected_cash', 130)
            ->assertJsonPath('data.difference', -5)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_cannot_open_two_sessions_on_one_register(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $register = CashRegister::create(['branch_id' => $owner->branch_id, 'name' => 'Front Desk']);

        $this->postJson("/api/v1/cash-registers/{$register->id}/open", ['opening_cash' => 0])->assertCreated();
        $this->postJson("/api/v1/cash-registers/{$register->id}/open", ['opening_cash' => 0])->assertStatus(422);
    }

    public function test_current_session_reflects_open_and_empty_state(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $register = CashRegister::create(['branch_id' => $owner->branch_id, 'name' => 'Front Desk']);

        $this->getJson("/api/v1/cash-registers/{$register->id}/current-session")
            ->assertOk()
            ->assertJsonPath('data', null);

        $session = $this->postJson("/api/v1/cash-registers/{$register->id}/open", ['opening_cash' => 50])
            ->assertCreated()->json('data');

        $this->getJson("/api/v1/cash-registers/{$register->id}/current-session")
            ->assertOk()
            ->assertJsonPath('data.id', $session['id']);
    }

    public function test_slip_pdf_can_be_streamed_for_open_and_closed_sessions(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $register = CashRegister::create(['branch_id' => $owner->branch_id, 'name' => 'Front Desk']);

        $session = $this->postJson("/api/v1/cash-registers/{$register->id}/open", ['opening_cash' => 100])
            ->assertCreated()->json('data');

        $this->get("/api/v1/cash-register-sessions/{$session['id']}/slip")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->postJson("/api/v1/cash-register-sessions/{$session['id']}/close", ['actual_cash' => 100])->assertOk();

        $this->get("/api/v1/cash-register-sessions/{$session['id']}/slip")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
