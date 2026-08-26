<?php

namespace Tests\Feature\Api;

use App\Models\PlatformSetting;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ModuleTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_user_can_get_enabled_modules(): void
    {
        $owner = $this->createTenant();
        app(TenantContext::class)->set($owner->tenant_id);

        PlatformSetting::set('modules', ['pos' => true, 'inventory' => true, 'hr' => false]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/modules');

        $response->assertOk();
        $response->assertJsonFragment(['pos' => true]);
        $response->assertJsonFragment(['inventory' => true]);
        $response->assertJsonMissing(['hr' => true]);
    }

    public function test_tenant_override_respected(): void
    {
        $owner = $this->createTenant();
        $tenantId = $owner->tenant_id;
        app(TenantContext::class)->set($tenantId);

        PlatformSetting::set('modules', ['pos' => true, 'inventory' => true]);
        PlatformSetting::set('tenant_overrides', [$tenantId => ['pos' => false]]);

        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/modules');

        $response->assertOk();
        $response->assertJsonFragment(['pos' => false]);
        $response->assertJsonFragment(['inventory' => true]);
    }
}
