<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class ApiRateLimitTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_report_endpoints_have_a_tighter_rate_limit_and_return_retry_after(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $last = null;
        for ($i = 0; $i < 21; $i++) {
            $last = $this->getJson('/api/v1/reports/sales-summary');
        }

        $last->assertStatus(429);
        $this->assertNotNull($last->headers->get('Retry-After'));
    }

    public function test_login_is_throttled_per_account_after_repeated_failures(): void
    {
        $email = 'ratelimit-'.uniqid().'@example.test';
        $owner = $this->createTenant($email);

        $last = null;
        for ($i = 0; $i < 6; $i++) {
            $last = $this->postJson('/api/v1/auth/login', [
                'email' => $owner->email, 'password' => 'wrong-password',
            ]);
        }

        $last->assertStatus(429);

        // A different account from the same request burst is unaffected by the per-email limiter.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'someone-else-'.uniqid().'@example.test', 'password' => 'wrong-password',
        ])->assertStatus(422);
    }
}
