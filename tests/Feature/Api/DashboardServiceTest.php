<?php

namespace Tests\Feature\Api;

use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DashboardService::class);
    }

    public function test_get_summary_returns_array(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $response = $this->getJson('/api/v1/dashboard/summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'todays_sales',
                    'todays_transactions',
                    'total_due',
                    'customer_dues',
                    'total_products',
                    'total_customers',
                    'low_stock_count',
                    'sales_trend',
                    'top_products',
                    'recent_sales',
                    'low_stock_items',
                    'week_comparison',
                ],
            ]);
    }
}
