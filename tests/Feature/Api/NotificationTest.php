<?php

namespace Tests\Feature\Api;

use App\Models\AppNotification;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_user_sees_and_marks_notifications_read(): void
    {
        $owner = $this->createTenant();
        app(TenantContext::class)->set($owner->tenant_id);

        AppNotification::create(['user_id' => null, 'type' => 'low_stock', 'title' => 'Low stock alert']);
        AppNotification::create(['user_id' => $owner->id, 'type' => 'due', 'title' => 'Payment due']);

        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.total', 2);
        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread', 2);

        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.marked', 2);
        $this->getJson('/api/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread', 0);
    }
}
