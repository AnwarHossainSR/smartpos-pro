<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    public function test_product_creation_is_blocked_when_plan_limit_reached(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $plan = Plan::create(['name' => 'Tiny', 'slug' => 'tiny', 'price' => 0,
            'limits' => ['products' => 1, 'users' => 5, 'branches' => 2, 'warehouses' => 3]]);
        app(TenantContext::class)->set($owner->tenant_id);
        Subscription::create(['plan_id' => $plan->id, 'status' => 'active']);

        $this->postJson('/api/v1/products', ['name' => 'First', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertCreated();

        $this->postJson('/api/v1/products', ['name' => 'Second', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertStatus(422)->assertJsonValidationErrors('plan');
    }

    public function test_user_creation_is_blocked_when_plan_limit_reached(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $plan = Plan::create(['name' => 'Tiny', 'slug' => 'tiny', 'price' => 0,
            'limits' => ['products' => 1, 'users' => 1, 'branches' => 2, 'warehouses' => 3]]);
        app(TenantContext::class)->set($owner->tenant_id);
        Subscription::create(['plan_id' => $plan->id, 'status' => 'active']);

        $this->postJson('/api/v1/users', [
            'name' => 'Test User', 'email' => 'test@example.test',
            'password' => 'password123', 'status' => 'active',
        ])->assertStatus(422)->assertJsonValidationErrors('plan');
    }

    public function test_subscription_usage_snapshot_is_returned(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $plan = Plan::create(['name' => 'Tiny', 'slug' => 'tiny', 'price' => 0,
            'limits' => ['products' => 1, 'users' => 5, 'branches' => 2, 'warehouses' => 3]]);
        app(TenantContext::class)->set($owner->tenant_id);
        Subscription::create(['plan_id' => $plan->id, 'status' => 'active']);

        $this->getJson('/api/v1/subscription/current')
            ->assertOk()
            ->assertJsonStructure(['data' => ['usage' => ['products' => ['used', 'limit'], 'users' => ['used', 'limit']]]]);
    }

    public function test_free_plan_has_generous_default_limits(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $plan = Plan::create(['name' => 'Free', 'slug' => 'free', 'price' => 0, 'limits' => null]);
        app(TenantContext::class)->set($owner->tenant_id);
        Subscription::create(['plan_id' => $plan->id, 'status' => 'active']);

        $this->postJson('/api/v1/products', ['name' => 'Product', 'purchase_price' => 1, 'selling_price' => 2])
            ->assertCreated();

        $this->postJson('/api/v1/users', [
            'name' => 'User', 'email' => 'user@example.test',
            'password' => 'password123', 'status' => 'active',
        ])->assertCreated();
    }
}
