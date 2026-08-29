<?php

namespace Tests\Feature\Api;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\Platform\ModuleRegistry;
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

    /**
     * Everything below covers enforcement rather than reporting.
     *
     * The two tests above assert what `/modules` *says*, which is all the toggles used
     * to do: the SPA hid the navigation and every endpoint stayed callable, so a tenant
     * whose accounting module was switched off could still call the accounting API
     * directly. Gating that only hides menu items is not gating, and nothing here
     * noticed — which is also how the middleware for it initially got attached to a
     * block of routes that never answers a request.
     */
    private function accountingRequest(): \Illuminate\Testing\TestResponse
    {
        return $this->getJson('/api/v1/accounts');
    }

    public function test_a_disabled_module_blocks_the_endpoint_not_just_the_menu(): void
    {
        $owner = $this->createTenant();
        app(TenantContext::class)->set($owner->tenant_id);

        PlatformSetting::set('modules', ['accounting' => false]);
        app(ModuleRegistry::class)->flush();

        Sanctum::actingAs($owner);

        // An owner holds every permission, so a 403 here can only be the module gate.
        $this->accountingRequest()
            ->assertForbidden()
            ->assertJsonPath('code', 'module_disabled')
            ->assertJsonPath('module', 'accounting');
    }

    public function test_a_tenant_override_can_switch_a_module_off_for_one_tenant(): void
    {
        $owner = $this->createTenant();
        $tenantId = $owner->tenant_id;
        app(TenantContext::class)->set($tenantId);

        PlatformSetting::set('modules', ['accounting' => true]);
        PlatformSetting::set('tenant_overrides', [$tenantId => ['accounting' => false]]);
        app(ModuleRegistry::class)->flush();

        Sanctum::actingAs($owner);

        $this->accountingRequest()->assertForbidden();
    }

    public function test_a_tenant_override_can_switch_a_module_on_that_is_off_by_default(): void
    {
        $owner = $this->createTenant();
        $tenantId = $owner->tenant_id;
        app(TenantContext::class)->set($tenantId);

        // The beta-rollout case, and the one that was impossible: `/modules` skipped
        // anything globally off *before* it ever consulted the override, so a module
        // could never be switched on for a single tenant.
        PlatformSetting::set('modules', ['accounting' => false]);
        PlatformSetting::set('tenant_overrides', [$tenantId => ['accounting' => true]]);
        app(ModuleRegistry::class)->flush();

        Sanctum::actingAs($owner);

        $this->accountingRequest()->assertOk();
        $this->getJson('/api/v1/modules')->assertOk()->assertJsonFragment(['accounting' => true]);
    }

    public function test_a_super_admin_is_not_gated_by_module_toggles(): void
    {
        $this->createTenant();

        PlatformSetting::set('modules', ['accounting' => false]);
        app(ModuleRegistry::class)->flush();

        $admin = User::forceCreate([
            'name' => 'Super Admin',
            'email' => 'super-modules@example.test',
            'password' => 'password123',
            'status' => 'active',
            'is_super_admin' => true,
        ]);

        Sanctum::actingAs($admin);

        // Platform staff have to be able to reach a module in order to turn it back on.
        //
        // Asserted as "not stopped by the gate" rather than as a 200: a super admin has
        // no tenant bound, so the tenant-scoped query behind this endpoint fails closed
        // by design. What happens after the gate is not this test's business.
        $response = $this->accountingRequest();

        $this->assertNotSame(
            'module_disabled',
            $response->json('code'),
            'A super admin must not be blocked by a module toggle.',
        );
    }
}
