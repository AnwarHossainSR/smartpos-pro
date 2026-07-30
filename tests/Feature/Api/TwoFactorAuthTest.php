<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\Auth\TotpService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class TwoFactorAuthTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private function codeFor(string $secret): string
    {
        $totp = app(TotpService::class);
        $ref = new \ReflectionMethod($totp, 'codeAt');
        $ref->setAccessible(true);
        $counter = (int) floor(time() / 30);

        return $ref->invoke($totp, $secret, $counter);
    }

    private function enrollAndConfirm(User $user): array
    {
        Sanctum::actingAs($user);
        $secret = $this->postJson('/api/v1/auth/2fa/enable')->assertOk()->json('data.secret');

        $response = $this->postJson('/api/v1/auth/2fa/confirm', ['code' => $this->codeFor($secret)])
            ->assertOk();

        return ['secret' => $secret, 'recovery_codes' => $response->json('data.recovery_codes')];
    }

    public function test_enabling_2fa_returns_a_secret_and_provisioning_uri(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->postJson('/api/v1/auth/2fa/enable')->assertOk();

        $this->assertNotEmpty($response->json('data.secret'));
        $this->assertStringContainsString('otpauth://totp/', $response->json('data.otpauth_url'));
        $this->assertFalse($owner->fresh()->hasEnabledTwoFactor());
    }

    public function test_confirming_with_a_wrong_code_does_not_enable_2fa(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        $this->postJson('/api/v1/auth/2fa/enable')->assertOk();

        $this->postJson('/api/v1/auth/2fa/confirm', ['code' => '000000'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('code');

        $this->assertFalse($owner->fresh()->hasEnabledTwoFactor());
    }

    public function test_confirming_with_the_correct_code_enables_2fa_and_returns_recovery_codes(): void
    {
        $owner = $this->createTenant();

        $result = $this->enrollAndConfirm($owner);

        $this->assertTrue($owner->fresh()->hasEnabledTwoFactor());
        $this->assertCount(8, $result['recovery_codes']);
    }

    public function test_login_with_2fa_enabled_requires_a_challenge_before_issuing_a_token(): void
    {
        $owner = $this->createTenant();
        $this->enrollAndConfirm($owner);
        auth()->forgetGuards();

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'password123',
        ])->assertOk();

        $this->assertTrue($response->json('requires_2fa'));
        $this->assertNotEmpty($response->json('challenge_token'));
        $this->assertNull($response->json('token'));
    }

    public function test_challenge_with_the_correct_totp_code_completes_login(): void
    {
        $owner = $this->createTenant();
        ['secret' => $secret] = $this->enrollAndConfirm($owner);
        auth()->forgetGuards();

        $challengeToken = $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'password123',
        ])->json('challenge_token');

        $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'code' => $this->codeFor($secret),
        ])->assertOk()->assertJsonStructure(['user', 'token']);
    }

    public function test_challenge_with_a_recovery_code_completes_login_and_the_code_is_single_use(): void
    {
        $owner = $this->createTenant();
        ['recovery_codes' => $codes] = $this->enrollAndConfirm($owner);
        auth()->forgetGuards();

        $challengeToken = $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'password123',
        ])->json('challenge_token');

        $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challengeToken,
            'recovery_code' => $codes[0],
        ])->assertOk();

        auth()->forgetGuards();
        $challengeToken2 = $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'password123',
        ])->json('challenge_token');

        $this->postJson('/api/v1/auth/2fa/challenge', [
            'challenge_token' => $challengeToken2,
            'recovery_code' => $codes[0],
        ])->assertStatus(422);
    }

    public function test_disabling_2fa_requires_the_correct_password(): void
    {
        $owner = $this->createTenant();
        $this->enrollAndConfirm($owner);

        $this->postJson('/api/v1/auth/2fa/disable', ['password' => 'wrong-password'])
            ->assertStatus(422);
        $this->assertTrue($owner->fresh()->hasEnabledTwoFactor());

        $this->postJson('/api/v1/auth/2fa/disable', ['password' => 'password123'])->assertOk();
        $this->assertFalse($owner->fresh()->hasEnabledTwoFactor());
    }

    public function test_progressive_lockout_after_repeated_failed_logins(): void
    {
        $owner = $this->createTenant();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $owner->email, 'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertTrue($owner->fresh()->isLocked());

        // The per-email rate limiter (5/min) is also exhausted by the same 5 attempts — clear
        // it directly so this request exercises the account-lockout check specifically, rather
        // than being caught by the (separate, already-covered) rate limiter first.
        RateLimiter::clear(md5('login'.'email:'.strtolower($owner->email)));

        $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'password123',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_lockout_releases_after_the_cooldown(): void
    {
        $owner = $this->createTenant();

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => $owner->email, 'password' => 'wrong-password',
            ]);
        }

        app(TenantContext::class)->set($owner->tenant_id);
        $this->assertTrue($owner->fresh()->isLocked());

        $this->travel(2)->minutes();

        $this->postJson('/api/v1/auth/login', [
            'email' => $owner->email, 'password' => 'password123',
        ])->assertOk();
    }
}
