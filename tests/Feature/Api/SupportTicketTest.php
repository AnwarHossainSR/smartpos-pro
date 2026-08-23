<?php

namespace Tests\Feature\Api;

use App\Models\SupportTicket;
use App\Support\Rbac\RbacProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(RbacProvisioner::class)->syncPermissions();
    }

    public function test_user_can_create_ticket(): void
    {
        $owner = $this->createTenant();
        $token = $owner->createToken('web')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/support/tickets', [
                'subject' => 'Test Subject',
                'message' => 'Test message body',
                'category' => 'technical',
                'priority' => 'high',
            ]);

        $response->assertCreated();
        $response->assertJsonFragment(['subject' => 'Test Subject']);
        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $owner->id,
            'subject' => 'Test Subject',
        ]);
    }

    public function test_user_can_list_own_tickets(): void
    {
        $owner = $this->createTenant();
        $token = $owner->createToken('web')->plainTextToken;

        SupportTicket::create([
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'subject' => 'My Ticket',
            'message' => 'Body',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/support/tickets');

        $response->assertOk();
        $response->assertJsonFragment(['subject' => 'My Ticket']);
    }

    public function test_user_can_view_own_ticket(): void
    {
        $owner = $this->createTenant();
        $token = $owner->createToken('web')->plainTextToken;

        $ticket = SupportTicket::create([
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'subject' => 'Viewable Ticket',
            'message' => 'Body',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/support/tickets/{$ticket->id}");

        $response->assertOk();
        $response->assertJsonFragment(['subject' => 'Viewable Ticket']);
    }

    public function test_user_can_reply_to_own_ticket(): void
    {
        $owner = $this->createTenant();
        $token = $owner->createToken('web')->plainTextToken;

        $ticket = SupportTicket::create([
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'subject' => 'Reply Ticket',
            'message' => 'Body',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/support/tickets/{$ticket->id}/replies", [
                'message' => 'This is my reply',
            ]);

        $response->assertCreated();
        $response->assertJsonFragment(['message' => 'This is my reply']);
        $this->assertDatabaseHas('ticket_replies', [
            'ticket_id' => $ticket->id,
            'user_id' => $owner->id,
        ]);
    }

    public function test_user_can_close_own_ticket(): void
    {
        $owner = $this->createTenant();
        $token = $owner->createToken('web')->plainTextToken;

        $ticket = SupportTicket::create([
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'subject' => 'Closeable Ticket',
            'message' => 'Body',
            'status' => 'open',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/support/tickets/{$ticket->id}/close");

        $response->assertOk();
        $ticket->refresh();
        $this->assertEquals('closed', $ticket->status);
    }

    public function test_user_can_reopen_own_ticket(): void
    {
        $owner = $this->createTenant();
        $token = $owner->createToken('web')->plainTextToken;

        $ticket = SupportTicket::create([
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'subject' => 'Reopen Ticket',
            'message' => 'Body',
            'status' => 'closed',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->putJson("/api/v1/support/tickets/{$ticket->id}/reopen");

        $response->assertOk();
        $ticket->refresh();
        $this->assertEquals('open', $ticket->status);
    }

    public function test_user_cannot_view_other_tenants_tickets(): void
    {
        $owner1 = $this->createTenant('owner1@example.test');
        $owner2 = $this->createTenant('owner2@example.test');

        $ticket = SupportTicket::create([
            'tenant_id' => $owner2->tenant_id,
            'user_id' => $owner2->id,
            'subject' => 'Other Tenant Ticket',
            'message' => 'Body',
        ]);

        $token = $owner1->createToken('web')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/support/tickets/{$ticket->id}");

        $response->assertNotFound();
    }

    public function test_unauthenticated_user_cannot_access_tickets(): void
    {
        $response = $this->getJson('/api/v1/support/tickets');
        $response->assertUnauthorized();

        $response = $this->postJson('/api/v1/support/tickets', [
            'subject' => 'Test',
            'message' => 'Test',
        ]);
        $response->assertUnauthorized();
    }
}
