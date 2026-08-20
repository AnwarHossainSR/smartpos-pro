<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_registration_provisions_a_tenant_business_and_owner(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane Owner',
            'email' => 'jane@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'business_name' => 'Jane Mart',
            'business_type' => 'retail',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.email', 'jane@example.test')
            ->assertJsonStructure(['user' => ['id', 'roles', 'permissions'], 'token']);

        $this->assertSame(1, Tenant::count());
        $this->assertDatabaseHas('businesses', ['name' => 'Jane Mart']);
        $this->assertDatabaseHas('branches', ['is_default' => true]);
        $this->assertDatabaseHas('warehouses', ['is_default' => true]);

        $owner = User::where('email', 'jane@example.test')->first();
        $this->assertTrue($owner->hasRole('owner'));
        $this->assertTrue($owner->hasPermission('sales.refund'));
    }

    public function test_login_returns_a_token_and_rejects_bad_credentials(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Jane', 'email' => 'jane@example.test', 'password' => 'password123',
            'password_confirmation' => 'password123', 'business_name' => 'Jane Mart',
        ])->assertCreated();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.test', 'password' => 'password123',
        ])->assertOk()->assertJsonStructure(['token']);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'jane@example.test', 'password' => 'wrong',
        ])->assertStatus(422);
    }

    public function test_me_requires_authentication(): void
    {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();
    }

    /**
     * The frontend's `can()` escalates on `is_super_admin` and reads its allow-list
     * from `permissions[]`, so both keys are part of the /auth/me contract rather
     * than incidental output — dropping either silently blanks the whole UI.
     */
    public function test_me_returns_the_identity_fields_the_frontend_gates_on(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'email', 'is_super_admin', 'roles', 'permissions']]);

        $this->assertFalse($response->json('data.is_super_admin'));
        $this->assertSame(['owner'], $response->json('data.roles'));
        $this->assertContains('tables.view', $response->json('data.permissions'));
    }
}
