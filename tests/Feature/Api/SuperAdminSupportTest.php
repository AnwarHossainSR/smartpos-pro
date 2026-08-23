<?php

namespace Tests\Feature\Api;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SuperAdminSupportTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private User $superAdmin;

    private User $tenantUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::forceCreate([
            'name' => 'Super Admin',
            'email' => 'super@example.test',
            'password' => 'password123',
            'status' => 'active',
            'is_super_admin' => true,
        ]);

        $owner = $this->createTenant();
        $this->tenantUser = $this->userWithRole($owner, 'cashier');
    }

    public function test_super_admin_can_list_all_tickets(): void
    {
        Sanctum::actingAs($this->superAdmin);

        SupportTicket::create([
            'tenant_id' => $this->tenantUser->tenant_id,
            'user_id' => $this->tenantUser->id,
            'subject' => 'Test Ticket',
            'message' => 'Test message body',
        ]);

        $response = $this->getJson('/api/v1/super/support/tickets');

        $response->assertOk();
        $response->assertJsonFragment(['subject' => 'Test Ticket']);
    }

    public function test_super_admin_can_view_any_ticket(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantUser->tenant_id,
            'user_id' => $this->tenantUser->id,
            'subject' => 'Detail Ticket',
            'message' => 'Detail message',
        ]);

        $response = $this->getJson("/api/v1/super/support/tickets/{$ticket->id}");

        $response->assertOk();
        $response->assertJsonPath('data.subject', 'Detail Ticket');
    }

    public function test_super_admin_can_update_ticket_status(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantUser->tenant_id,
            'user_id' => $this->tenantUser->id,
            'subject' => 'Status Ticket',
            'message' => 'Status message',
        ]);

        $response = $this->putJson("/api/v1/super/support/tickets/{$ticket->id}/status", [
            'status' => 'in_progress',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', 'in_progress');
    }

    public function test_super_admin_can_add_internal_note(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $ticket = SupportTicket::create([
            'tenant_id' => $this->tenantUser->tenant_id,
            'user_id' => $this->tenantUser->id,
            'subject' => 'Note Ticket',
            'message' => 'Note message',
        ]);

        $response = $this->postJson("/api/v1/super/support/tickets/{$ticket->id}/notes", [
            'message' => 'Internal note content',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.message', 'Internal note content');
        $response->assertJsonPath('data.is_internal', true);
    }

    public function test_super_admin_can_get_metrics(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/v1/super/support/tickets/metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'total_open',
                'total_in_progress',
                'total_waiting',
                'total_resolved',
                'total_closed',
                'sla_breached',
            ],
        ]);
    }

    public function test_non_super_admin_cannot_list_tickets(): void
    {
        $user = User::forceCreate([
            'name' => 'Regular',
            'email' => 'regular@test.test',
            'password' => 'password123',
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/super/support/tickets');

        $this->assertNotEquals(200, $response->status());
    }
}
