<?php

namespace Tests\Feature\Api;

use App\Domain\Enums\SubscriptionStatus;
use App\Models\Plan;
use App\Models\Subscription;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SubscriptionCheckoutTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Plan::create(['name' => 'Starter', 'slug' => 'starter', 'price' => 1499,
            'limits' => ['branches' => 2, 'warehouses' => 2, 'users' => 5, 'products' => 500], 'is_active' => true]);
        Plan::create(['name' => 'Professional', 'slug' => 'professional', 'price' => 3499,
            'limits' => ['branches' => 5, 'warehouses' => 5, 'users' => 20, 'products' => 5000], 'is_active' => true]);
    }

    public function test_user_can_view_plans(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/subscription/plans')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_user_can_checkout_with_valid_plan(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $plan = Plan::where('slug', 'starter')->first();

        $this->postJson('/api/v1/subscription/checkout', ['plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonFragment(['status' => SubscriptionStatus::Active->value])
            ->assertJsonFragment(['trial_ends_at' => null]);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $owner->tenant_id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active->value,
            'trial_ends_at' => null,
        ]);
    }

    public function test_user_cannot_checkout_with_invalid_plan(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/subscription/checkout', ['plan_id' => 'nonexistent'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_user_cannot_checkout_with_inactive_plan(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);

        $plan = Plan::create(['name' => 'Hidden', 'slug' => 'hidden', 'price' => 999, 'limits' => [], 'is_active' => false]);

        $this->postJson('/api/v1/subscription/checkout', ['plan_id' => $plan->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan_id');
    }

    public function test_existing_subscription_gets_upgraded(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $starter = Plan::where('slug', 'starter')->first();
        $professional = Plan::where('slug', 'professional')->first();

        Subscription::create([
            'plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => now()->addDays(30),
        ]);

        $this->postJson('/api/v1/subscription/checkout', ['plan_id' => $professional->id])
            ->assertCreated()
            ->assertJsonFragment(['status' => SubscriptionStatus::Active->value]);

        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $owner->tenant_id,
            'plan_id' => $professional->id,
            'status' => SubscriptionStatus::Active->value,
        ]);
    }

    public function test_cannot_downgrade_subscription(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $starter = Plan::where('slug', 'starter')->first();
        $professional = Plan::where('slug', 'professional')->first();

        Subscription::create([
            'plan_id' => $professional->id,
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => now()->addDays(30),
        ]);

        $this->postJson('/api/v1/subscription/checkout', ['plan_id' => $starter->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }

    public function test_cannot_checkout_same_plan_twice(): void
    {
        $owner = $this->createTenant();
        Sanctum::actingAs($owner);
        app(TenantContext::class)->set($owner->tenant_id);

        $starter = Plan::where('slug', 'starter')->first();

        Subscription::create([
            'plan_id' => $starter->id,
            'status' => SubscriptionStatus::Active,
            'current_period_ends_at' => now()->addDays(30),
        ]);

        $this->postJson('/api/v1/subscription/checkout', ['plan_id' => $starter->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('plan');
    }
}
