<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\User;
use App\Support\Rbac\RbacProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SuperAdminPlanTest extends TestCase
{
    use CreatesTenants, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(RbacProvisioner::class)->syncPermissions();
    }

    private function superAdmin(): User
    {
        return User::forceCreate([
            'name' => 'Super Admin',
            'email' => 'super@example.test',
            'password' => 'password123',
            'status' => 'active',
            'is_super_admin' => true,
        ]);
    }

    private function createPlan(): Plan
    {
        return Plan::create([
            'name' => 'Pro Plan',
            'slug' => 'pro-'.uniqid(),
            'price' => 49.99,
            'interval' => 'month',
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_list_plans(): void
    {
        $admin = $this->superAdmin();
        $plan = $this->createPlan();

        $response = $this->actingAs($admin)
            ->getJson('/api/v1/super/plans');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $plan->id]);
    }

    public function test_super_admin_can_create_plan(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/super/plans', [
                'name' => 'Enterprise',
                'price' => 99.99,
                'interval' => 'month',
                'features' => ['pos', 'inventory', 'reports'],
            ]);

        $response->assertCreated();
        $response->assertJsonFragment(['name' => 'Enterprise']);
        $this->assertDatabaseHas('plans', ['name' => 'Enterprise', 'price' => 99.99]);
    }

    public function test_super_admin_can_update_plan(): void
    {
        $admin = $this->superAdmin();
        $plan = $this->createPlan();

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/super/plans/{$plan->id}", [
                'name' => 'Pro Plan Updated',
                'price' => 59.99,
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Pro Plan Updated']);
        $plan->refresh();
        $this->assertEquals('59.99', $plan->price);
    }

    public function test_super_admin_can_delete_plan(): void
    {
        $admin = $this->superAdmin();
        $plan = $this->createPlan();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/super/plans/{$plan->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('plans', ['id' => $plan->id]);
    }

    public function test_super_admin_can_assign_plan_to_tenant(): void
    {
        $admin = $this->superAdmin();
        $plan = $this->createPlan();
        $owner = $this->createTenant();
        $tenant = $owner->tenant;

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/super/tenants/{$tenant->id}/assign-plan", [
                'plan_id' => $plan->id,
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['plan_id' => $plan->id]);
        $this->assertDatabaseHas('subscriptions', [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $tenant->refresh();
        $this->assertEquals($plan->slug, $tenant->plan);
    }

    public function test_non_super_admin_cannot_list_plans(): void
    {
        $owner = $this->createTenant();
        $cashier = $this->userWithRole($owner, 'cashier');
        $token = $cashier->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/super/plans');

        $this->assertNotEquals(200, $response->status());
    }

    public function test_create_plan_validates_required_fields(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/super/plans', []);

        $response->assertUnprocessable();
    }
}
