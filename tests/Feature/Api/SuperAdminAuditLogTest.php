<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Rbac\RbacProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesTenants;
use Tests\TestCase;

class SuperAdminAuditLogTest extends TestCase
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

    public function test_creating_tenant_logs_tenant_created(): void
    {
        $admin = $this->superAdmin();
        $admin->createToken('web');

        AuditLog::withoutTenancy()->truncate();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/super/tenants', [
                'owner_name' => 'Acme Owner',
                'owner_email' => 'owner@acme.test',
                'owner_password' => 'secret123',
                'owner_password_confirmation' => 'secret123',
                'business_name' => 'Acme Store',
                'tenant_name' => 'Acme',
                'slug' => 'acme-'.uniqid(),
            ]);

        $response->assertCreated();

        AuditLog::withoutTenancy()->where('action', 'tenant.created')->firstOrFail();
        $log = AuditLog::withoutTenancy()->where('action', 'tenant.created')->first();
        $this->assertNotNull($log);
        $this->assertEquals('App\Models\Tenant', $log->auditable_type);
        $this->assertNotNull($log->auditable_id);
        $this->assertArrayHasKey('name', $log->new_values);
        $this->assertArrayHasKey('slug', $log->new_values);
    }

    public function test_suspend_tenant_logs_tenant_suspended(): void
    {
        $admin = $this->superAdmin();
        $tenant = Tenant::create([
            'name' => 'Suspend Me',
            'slug' => 'suspend-'.uniqid(),
            'status' => 'active',
        ]);

        AuditLog::withoutTenancy()->truncate();

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/super/tenants/{$tenant->id}/suspend");

        $response->assertOk();

        $log = AuditLog::withoutTenancy()->where('action', 'tenant.suspended')->first();
        $this->assertNotNull($log);
        $this->assertEquals($tenant->id, $log->auditable_id);
    }

    public function test_start_impersonation_logs_impersonation_started(): void
    {
        $admin = $this->superAdmin();
        $owner = $this->createTenant();
        $tenant = $owner->tenant;

        AuditLog::withoutTenancy()->truncate();

        // Points at the impersonation flow that actually ships. This used to call
        // `POST /super/impersonate`, a second implementation that handed back a cache
        // key nothing authenticated against and skipped the reason, the Impersonation
        // record, the owner's notification and the email — and whose route lived in a
        // file that was never loaded, so it has always 404'd. That endpoint is gone;
        // what the test is really asserting — that starting a support session leaves an
        // audit trail — is now checked against the real thing.
        $response = $this->actingAs($admin)
            ->postJson("/api/v1/super/tenants/{$tenant->id}/impersonate", [
                'reason' => 'Testing impersonation audit log functionality',
            ]);

        $response->assertCreated();

        $log = AuditLog::withoutTenancy()->where('action', 'impersonation.started')->first();
        $this->assertNotNull($log);
        $this->assertEquals($tenant->id, $log->tenant_id);
        $this->assertArrayHasKey('target_user_id', $log->new_values);
        $this->assertEquals($owner->id, $log->new_values['target_user_id']);
    }

    public function test_updating_tenant_logs_tenant_updated(): void
    {
        $admin = $this->superAdmin();
        $tenant = Tenant::create([
            'name' => 'Old Name',
            'slug' => 'old-'.uniqid(),
            'status' => 'active',
        ]);

        AuditLog::withoutTenancy()->truncate();

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/super/tenants/{$tenant->id}", [
                'name' => 'New Name',
            ]);

        $response->assertOk();

        $log = AuditLog::withoutTenancy()->where('action', 'tenant.updated')->first();
        $this->assertNotNull($log);
        $this->assertEquals($tenant->id, $log->auditable_id);
        $this->assertArrayHasKey('name', $log->old_values);
        $this->assertEquals('Old Name', $log->old_values['name']);
        $this->assertEquals('New Name', $log->new_values['name']);
    }

    public function test_creating_plan_logs_plan_created(): void
    {
        $admin = $this->superAdmin();

        AuditLog::withoutTenancy()->truncate();

        $response = $this->actingAs($admin)
            ->postJson('/api/v1/super/plans', [
                'name' => 'Enterprise',
                'price' => 99.99,
                'interval' => 'month',
            ]);

        $response->assertCreated();

        $log = AuditLog::withoutTenancy()->where('action', 'plan.created')->first();
        $this->assertNotNull($log);
        $this->assertEquals('App\Models\Plan', $log->auditable_type);
        $this->assertArrayHasKey('name', $log->new_values);
        $this->assertEquals('Enterprise', $log->new_values['name']);
    }

    public function test_deleting_plan_logs_plan_deleted(): void
    {
        $admin = $this->superAdmin();
        $plan = Plan::create([
            'name' => 'Delete Me',
            'slug' => 'delete-'.uniqid(),
            'price' => 19.99,
            'interval' => 'month',
            'is_active' => true,
        ]);

        AuditLog::withoutTenancy()->truncate();

        $response = $this->actingAs($admin)
            ->deleteJson("/api/v1/super/plans/{$plan->id}");

        $response->assertNoContent();

        $log = AuditLog::withoutTenancy()->where('action', 'plan.deleted')->first();
        $this->assertNotNull($log);
        $this->assertEquals($plan->id, $log->auditable_id);
        $this->assertEquals('Delete Me', $log->old_values['name']);
    }

    public function test_assigning_plan_logs_plan_assigned(): void
    {
        $admin = $this->superAdmin();
        $plan = Plan::create([
            'name' => 'Assign Plan',
            'slug' => 'assign-'.uniqid(),
            'price' => 29.99,
            'interval' => 'month',
            'is_active' => true,
        ]);
        $owner = $this->createTenant();
        $tenant = $owner->tenant;

        AuditLog::withoutTenancy()->truncate();

        $response = $this->actingAs($admin)
            ->postJson("/api/v1/super/tenants/{$tenant->id}/assign-plan", [
                'plan_id' => $plan->id,
            ]);

        $response->assertOk();

        $log = AuditLog::withoutTenancy()->where('action', 'plan.assigned')->first();
        $this->assertNotNull($log);
        $this->assertEquals($tenant->id, $log->auditable_id);
        $this->assertEquals($plan->id, $log->new_values['plan_id']);
    }
}
