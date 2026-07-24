<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_product_creation_is_blocked_when_plan_limit_reached(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        // Put the tenant on a plan that allows just one product.
        $plan = Plan::create(['name' => 'Tiny', 'slug' => 'tiny', 'price' => 0,
            'limits' => ['products' => 1, 'users' => 5, 'branches' => 2, 'warehouses' => 3]]);
        app(TenantContext::class)->set($owner->tenant_id);
        Subscription::create(['plan_id' => $plan->id, 'status' => 'active']);

        $this->postJson('/api/v1/products', ['name' => 'First', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertCreated();

        $this->postJson('/api/v1/products', ['name' => 'Second', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertStatus(422)->assertJsonValidationErrors('plan');
    }

    public function test_subscription_usage_snapshot_is_returned(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/subscription/current')
            ->assertOk()
            ->assertJsonStructure(['data' => ['usage' => ['products' => ['used', 'limit']]]]);
    }
}
