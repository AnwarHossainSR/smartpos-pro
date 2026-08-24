<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\AuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
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
                'user' => ['id', 'name', 'email'],
                'token',
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

    public function test_change_password_rejects_wrong_current_password(): void
    {
        $owner = $this->createTenant('owner@example.com');

        $response = $this->actingAs($owner)
            ->postJson('/api/v1/auth/change-password', [
                'current_password' => 'wrongpassword',
                'new_password' => 'newpassword',
                'new_password_confirmation' => 'newpassword',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password']);
    }

    public function test_account_locks_after_five_failed_attempts(): void
    {
        $owner = $this->createTenant('lockout@example.com');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'lockout@example.com',
                'password' => 'wrongpassword',
            ]);
        }

        $this->assertTrue($owner->fresh()->isLocked());

        $service = app(AuthService::class);
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Too many failed attempts');
        $service->login('lockout@example.com', 'password123', 'web', '127.0.0.1');
    }

    public function test_forgot_password_sends_reset_link(): void
    {
        $owner = $this->createTenant('reset@example.com');

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertOk();
    }

    public function test_reset_password_with_valid_token(): void
    {
        $owner = $this->createTenant('reset@example.com');

        $token = Password::createToken($owner);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token' => $token,
        ]);

        $response->assertOk();

        // Old password should no longer work
        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'password123',
        ])->assertUnprocessable();

        // New password should work
        $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
        ])->assertOk();
    }

    public function test_reset_password_rejects_invalid_token(): void
    {
        $owner = $this->createTenant('reset@example.com');

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token' => 'invalid-token',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_verify_email_otp_with_valid_code(): void
    {
        $owner = User::withoutTenancy()->where('email', 'verify@example.com')->first();
        if (! $owner) {
            $owner = $this->createTenant('verify@example.com');
        }
        // Force unverified
        $owner->forceFill(['email_verified_at' => null])->save();

        $code = $owner->generateEmailVerificationOtp();

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'email' => 'verify@example.com',
            'code' => $code,
        ]);

        $response->assertOk();
        $this->assertTrue($owner->fresh()->hasVerifiedEmail());
    }

    public function test_verify_email_otp_rejects_invalid_code(): void
    {
        $owner = $this->createTenant('verify@example.com');
        $owner->forceFill(['email_verified_at' => null])->save();
        $owner->generateEmailVerificationOtp();

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'email' => 'verify@example.com',
            'code' => '000000',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_verify_email_otp_skips_already_verified(): void
    {
        $owner = $this->createTenant('alreadyverified@example.com');

        $response = $this->postJson('/api/v1/auth/email/verify', [
            'email' => 'alreadyverified@example.com',
            'code' => '000000',
        ]);

        $response->assertOk();
    }

    public function test_ip_address_is_recorded_on_token(): void
    {
        $owner = $this->createTenant('ip@example.com');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'ip@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        $plainToken = $response->json('token');
        $this->assertNotNull($plainToken);

        $tokenId = explode('|', $plainToken)[0];
        $accessToken = $owner->tokens()->whereKey($tokenId)->first();
        $this->assertNotNull($accessToken);
        $this->assertEquals('127.0.0.1', $accessToken->ip_address);
    }
}
