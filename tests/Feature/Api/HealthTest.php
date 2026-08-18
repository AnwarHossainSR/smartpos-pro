<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_health_endpoint_returns_ok_when_all_services_are_up(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        Redis::shouldReceive('ping')->andReturn(true);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'ok',
                'checks' => [
                    'database' => true,
                    'redis' => true,
                    'queue' => true,
                ],
            ])
            ->assertJsonStructure(['timestamp']);
    }

    public function test_health_endpoint_database_check_passes(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        Redis::shouldReceive('ping')->andReturn(true);

        $response = $this->getJson('/api/v1/health');

        $response->assertOk()
            ->assertJsonPath('checks.database', true);
    }

    public function test_health_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertUnauthorized();
    }
}
