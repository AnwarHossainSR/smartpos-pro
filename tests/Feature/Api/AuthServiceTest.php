<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class AuthServiceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_login_returns_token_and_user(): void
    {
        $owner = $this->createTenant('test@example.com');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['user', 'token'],
            ]);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_change_password_works(): void
    {
        $owner = $this->createTenant('owner@example.com');

        $response = $this->actingAs($owner)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'password123',
                'new_password' => 'newpassword',
                'new_password_confirmation' => 'newpassword',
            ]);

        $response->assertOk();
    }
}
