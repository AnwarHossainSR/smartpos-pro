<?php

namespace Tests\Feature\Api;

use App\Jobs\DeliverWebhookJob;
use App\Models\Role;
use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_create_webhook(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => ['sale.completed', 'customer.created'],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.url', 'https://example.com/webhook')
            ->assertJsonPath('data.events', ['sale.completed', 'customer.created'])
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure(['data' => ['id', 'url', 'events', 'is_active', 'created_at', 'updated_at'], 'secret']);

        $this->assertDatabaseHas('webhooks', [
            'tenant_id' => $owner->tenant_id,
            'url' => 'https://example.com/webhook',
            'is_active' => true,
        ]);
    }

    public function test_list_webhooks(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        Webhook::create([
            'tenant_id' => $owner->tenant_id,
            'url' => 'https://example.com/hook1',
            'events' => ['sale.completed'],
            'secret' => 'secret1',
        ]);

        $this->getJson('/api/v1/webhooks')
            ->assertOk()
            ->assertJsonPath('data.0.url', 'https://example.com/hook1');
    }

    public function test_update_webhook(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $webhook = Webhook::create([
            'tenant_id' => $owner->tenant_id,
            'url' => 'https://example.com/old',
            'events' => ['sale.completed'],
            'secret' => 'secret1',
        ]);

        $this->putJson("/api/v1/webhooks/{$webhook->id}", [
            'url' => 'https://example.com/new',
            'events' => ['sale.returned'],
        ])->assertOk()->assertJsonPath('data.url', 'https://example.com/new');

        $this->assertDatabaseHas('webhooks', ['id' => $webhook->id, 'url' => 'https://example.com/new']);
    }

    public function test_delete_webhook(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $webhook = Webhook::create([
            'tenant_id' => $owner->tenant_id,
            'url' => 'https://example.com/hook',
            'events' => ['sale.completed'],
            'secret' => 'secret1',
        ]);

        $this->deleteJson("/api/v1/webhooks/{$webhook->id}")->assertNoContent();

        $this->assertDatabaseMissing('webhooks', ['id' => $webhook->id]);
    }

    public function test_dispatch_fires_job_for_subscribed_webhooks(): void
    {
        Queue::fake();

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $webhook = Webhook::create([
            'tenant_id' => $owner->tenant_id,
            'url' => 'https://example.com/hook',
            'events' => ['sale.completed', 'inventory.low'],
            'secret' => WebhookService::generateSecret(),
            'is_active' => true,
        ]);

        Webhook::create([
            'tenant_id' => $owner->tenant_id,
            'url' => 'https://example.com/unsubscribed',
            'events' => ['customer.created'],
            'secret' => 'other',
            'is_active' => true,
        ]);

        app(WebhookService::class)->dispatch('sale.completed', ['sale_id' => 'test-123', 'total' => 100]);

        Queue::assertPushed(DeliverWebhookJob::class, 1);
        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $webhook->id,
            'event' => 'sale.completed',
        ]);
    }

    public function test_inactive_webhooks_are_not_fired(): void
    {
        Queue::fake();

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        Webhook::create([
            'tenant_id' => $owner->tenant_id,
            'url' => 'https://example.com/inactive',
            'events' => ['sale.completed'],
            'secret' => 'secret',
            'is_active' => false,
        ]);

        app(WebhookService::class)->dispatch('sale.completed', ['sale_id' => 'test']);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('webhook_deliveries', 0);
    }

    public function test_hmac_signature_is_correct(): void
    {
        $secret = 'test-secret-key';
        $payload = '{"sale_id":"abc","total":100}';

        $signature = WebhookService::sign($secret, $payload);

        $this->assertEquals(hash_hmac('sha256', $payload, $secret), $signature);
    }

    public function test_delivery_log_is_listed(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $webhook = Webhook::create([
            'tenant_id' => $owner->tenant_id,
            'url' => 'https://example.com/hook',
            'events' => ['sale.completed'],
            'secret' => 'secret',
        ]);

        WebhookDelivery::create([
            'tenant_id' => $owner->tenant_id,
            'webhook_id' => $webhook->id,
            'event' => 'sale.completed',
            'payload' => ['sale_id' => 'test'],
            'response_code' => 200,
            'delivered_at' => now(),
            'attempts' => 1,
        ]);

        $this->getJson('/api/v1/webhook-deliveries')
            ->assertOk()
            ->assertJsonPath('data.0.event', 'sale.completed')
            ->assertJsonPath('data.0.response_code', 200);
    }

    public function test_retry_delivery_creates_new_delivery_and_dispatches_job(): void
    {
        Queue::fake();

        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $webhook = Webhook::create([
            'tenant_id' => $owner->tenant_id,
            'url' => 'https://example.com/hook',
            'events' => ['sale.completed'],
            'secret' => WebhookService::generateSecret(),
            'is_active' => true,
        ]);

        $delivery = WebhookDelivery::create([
            'tenant_id' => $owner->tenant_id,
            'webhook_id' => $webhook->id,
            'event' => 'sale.completed',
            'payload' => ['sale_id' => 'test'],
            'response_code' => 500,
            'attempts' => 3,
        ]);

        $this->postJson("/api/v1/webhook-deliveries/{$delivery->id}/retry")
            ->assertCreated()
            ->assertJsonPath('data.event', 'sale.completed');

        Queue::assertPushed(DeliverWebhookJob::class);
        $this->assertDatabaseHas('webhook_deliveries', [
            'webhook_id' => $webhook->id,
            'attempts' => 0,
        ]);
    }

    public function test_cannot_create_webhook_with_invalid_event(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com/webhook',
            'events' => ['invalid.event'],
        ])->assertUnprocessable();
    }

    public function test_cannot_create_webhook_without_url(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/webhooks', [
            'events' => ['sale.completed'],
        ])->assertUnprocessable();
    }

    public function test_webhook_requires_settings_update_permission(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->actingAs($this->createCashier($owner));

        $this->getJson('/api/v1/webhooks')->assertForbidden();
        $this->postJson('/api/v1/webhooks', [
            'url' => 'https://example.com',
            'events' => ['sale.completed'],
        ])->assertForbidden();
    }

    protected function createCashier($owner)
    {
        // `roles.name` holds the slug; `roles.title` the human label.
        $cashierRole = Role::where('tenant_id', $owner->tenant_id)
            ->where('name', 'cashier')->first();

        if (! $cashierRole) {
            $cashierRole = Role::create([
                'tenant_id' => $owner->tenant_id,
                'name' => 'cashier',
                'title' => 'Cashier',
            ]);
            $cashierRole->syncPermissions([
                'dashboard.view', 'products.view', 'pos.view', 'pos.create',
                'sales.view', 'customers.view', 'customers.create',
            ]);
        }

        $cashier = User::create([
            'business_id' => $owner->business_id,
            'name' => 'Cashier',
            'email' => 'cashier@test.com',
            'password' => bcrypt('password'),
        ]);

        $cashier->forceFill(['tenant_id' => $owner->tenant_id, 'email_verified_at' => now()])->save();

        $cashier->assignRole($cashierRole);

        return $cashier;
    }
}
