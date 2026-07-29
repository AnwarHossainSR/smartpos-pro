<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class TenantSuspensionTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function superAdmin(): User
    {
        return User::forceCreate([
            'name' => 'Super Admin', 'email' => 'super@example.test',
            'password' => 'password123', 'status' => 'active', 'is_super_admin' => true,
        ]);
    }

    public function test_suspending_a_tenant_revokes_its_live_tokens(): void
    {
        $owner = $this->createTenant();
        $token = $owner->createToken('web')->plainTextToken;
        $superToken = $this->superAdmin()->createToken('web')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$superToken}")
            ->postJson("/api/v1/super/tenants/{$owner->tenant_id}/suspend")
            ->assertOk();

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_ensure_tenant_middleware_blocks_a_suspended_tenant_with_machine_readable_code(): void
    {
        $owner = $this->createTenant();
        $token = $owner->createToken('web')->plainTextToken;

        Tenant::whereKey($owner->tenant_id)->update(['status' => 'suspended']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertForbidden()
            ->assertJsonPath('code', 'tenant_suspended');
    }

    public function test_login_is_refused_for_a_suspended_tenant(): void
    {
        $owner = $this->createTenant();
        Tenant::whereKey($owner->tenant_id)->update(['status' => 'suspended']);

        $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'password123',
        ])->assertForbidden()->assertJsonPath('code', 'tenant_suspended');
    }

    public function test_reactivating_a_tenant_restores_access(): void
    {
        $owner = $this->createTenant();

        Sanctum::actingAs($this->superAdmin());
        $this->postJson("/api/v1/super/tenants/{$owner->tenant_id}/suspend")->assertOk();
        $this->postJson("/api/v1/super/tenants/{$owner->tenant_id}/activate")->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'password123',
        ])->assertOk();
    }
}
