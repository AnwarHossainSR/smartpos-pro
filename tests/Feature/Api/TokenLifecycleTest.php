<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class TokenLifecycleTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function cashierFor(User $owner): User
    {
        $cashier = User::create([
            'business_id' => $owner->business_id,
            'branch_id' => $owner->branch_id,
            'name' => 'Cashier', 'email' => 'cashier@example.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $cashier->assignRole('cashier');

        return $cashier->fresh('roles');
    }

    public function test_owner_login_issues_a_wildcard_ability_token(): void
    {
        $owner = $this->createTenant();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'password123',
        ])->assertOk();

        $plainToken = $response->json('token');
        $tokenId = explode('|', $plainToken)[0];
        $accessToken = PersonalAccessToken::find($tokenId);

        $this->assertSame(['*'], $accessToken->abilities);
        $this->assertNotNull($accessToken->ip_address);
    }

    public function test_a_scoped_role_gets_only_its_permission_slugs_as_abilities(): void
    {
        $owner = $this->createTenant();
        $this->cashierFor($owner);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'cashier@example.test', 'password' => 'password123',
        ])->assertOk();

        $plainToken = $response->json('token');
        $tokenId = explode('|', $plainToken)[0];
        $accessToken = PersonalAccessToken::find($tokenId);

        $this->assertNotContains('*', $accessToken->abilities);
        $this->assertContains('pos.create', $accessToken->abilities);
        $this->assertNotContains('users.delete', $accessToken->abilities);
    }

    public function test_sessions_endpoint_lists_active_devices(): void
    {
        $owner = $this->createTenant();
        $token = $owner->createToken('phone')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'phone')
            ->assertJsonPath('data.0.current', true);
    }

    public function test_revoking_a_session_invalidates_only_that_token(): void
    {
        $owner = $this->createTenant();
        $tokenA = $owner->createToken('laptop')->plainTextToken;
        $tokenB = $owner->createToken('phone');
        $tokenBPlain = $tokenB->plainTextToken;
        $tokenBId = $tokenB->accessToken->id;

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->deleteJson("/api/v1/auth/sessions/{$tokenBId}")
            ->assertOk();

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$tokenBPlain}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();
    }

    public function test_revoke_all_sessions_keeps_the_current_token(): void
    {
        $owner = $this->createTenant();
        $tokenA = $owner->createToken('laptop')->plainTextToken;
        $tokenB = $owner->createToken('phone')->plainTextToken;

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->postJson('/api/v1/auth/sessions/revoke-all')
            ->assertOk();

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$tokenA}")
            ->getJson('/api/v1/auth/me')
            ->assertOk();

        auth()->forgetGuards();
        $this->withHeader('Authorization', "Bearer {$tokenB}")
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }

    public function test_authenticating_with_a_token_works_before_any_tenant_is_bound(): void
    {
        // Regression: Sanctum resolves the token's owner (a tenant-scoped User) before the
        // `tenant` route middleware has bound anything into TenantContext, on every request —
        // not just in this test. Simulate that "cold" state explicitly instead of relying on
        // whatever createTenant() left bound, since a real request never starts pre-bound.
        $owner = $this->createTenant();
        $token = $owner->createToken('phone')->plainTextToken;

        app(TenantContext::class)->forget();
        auth()->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', $owner->email);
    }

    public function test_a_user_cannot_revoke_another_users_session(): void
    {
        $owner = $this->createTenant();
        $cashier = $this->cashierFor($owner);
        $cashierToken = $cashier->createToken('cashier-device');

        Sanctum::actingAs($owner);
        $this->deleteJson("/api/v1/auth/sessions/{$cashierToken->accessToken->id}")
            ->assertNotFound();
    }
}
