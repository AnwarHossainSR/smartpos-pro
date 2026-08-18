<?php

namespace Tests\Feature\Api;

use App\Models\Shift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_clock_in_and_clock_out(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/shifts/clock-in', ['branch_id' => $owner->branch_id]);
        $response->assertCreated()->assertJsonPath('data.status', 'open');

        $response = $this->postJson('/api/v1/shifts/clock-out');
        $response->assertOk()->assertJsonPath('data.status', 'closed');
        $this->assertNotNull($response->json('data.worked_minutes'));
    }

    public function test_double_clock_in_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/shifts/clock-in', ['branch_id' => $owner->branch_id])->assertCreated();
        $this->postJson('/api/v1/shifts/clock-in')->assertStatus(422);
    }

    public function test_clock_out_without_open_shift_rejected(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/shifts/clock-out')->assertStatus(422);
    }

    public function test_me_endpoint_returns_current_shift(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/shifts/me')->assertOk()->assertJsonPath('data', null);

        $this->postJson('/api/v1/shifts/clock-in', ['branch_id' => $owner->branch_id])->assertCreated();

        $this->getJson('/api/v1/shifts/me')
            ->assertOk()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_index_lists_own_shifts(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/shifts/clock-in', ['branch_id' => $owner->branch_id])->assertCreated();
        $this->postJson('/api/v1/shifts/clock-out')->assertOk();

        $this->getJson('/api/v1/shifts')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_shifts_report_totals(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/shifts/clock-in', ['branch_id' => $owner->branch_id])->assertCreated();
        $this->postJson('/api/v1/shifts/clock-out')->assertOk();

        $from = now()->startOfDay()->toDateString();
        $to = now()->endOfDay()->toDateString();

        $response = $this->getJson("/api/v1/reports/shifts?from={$from}&to={$to}")
            ->assertOk();

        $this->assertNotEmpty($response->json('data.rows'));
        $this->assertGreaterThan(0, $response->json('data.totals.shifts'));
    }

    public function test_shifts_report_overtime_flag(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        // Create a shift with 9 hours of work (over 8h threshold)
        Shift::create([
            'user_id' => $owner->id,
            'branch_id' => $owner->branch_id,
            'clock_in' => now()->subHours(9),
            'clock_out' => now(),
            'worked_minutes' => 540,
            'status' => 'closed',
        ]);

        $from = now()->startOfDay()->toDateString();
        $to = now()->endOfDay()->toDateString();

        $response = $this->getJson("/api/v1/reports/shifts?from={$from}&to={$to}")
            ->assertOk();

        $row = $response->json('data.rows.0');
        $this->assertTrue($row['is_overtime']);
        $this->assertGreaterThan(0, $row['overtime_minutes']);
    }
}
