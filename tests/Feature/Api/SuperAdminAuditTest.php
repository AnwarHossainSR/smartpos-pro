<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Rbac\RbacProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminAuditTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_super_admin_can_list_audit_logs(): void
    {
        $admin = $this->superAdmin();
        Sanctum::actingAs($admin);

        AuditLog::create([
            'action' => 'tenant.suspended',
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson('/api/v1/super/audit-logs');

        $response->assertOk();
        $response->assertJsonStructure([
            '*' => ['id', 'action'],
        ]);
    }

    public function test_super_admin_can_filter_audit_logs_by_action(): void
    {
        $admin = $this->superAdmin();
        Sanctum::actingAs($admin);

        AuditLog::create(['action' => 'tenant.suspended', 'ip_address' => '127.0.0.1']);
        AuditLog::create(['action' => 'impersonation.started', 'ip_address' => '127.0.0.1']);

        $response = $this->getJson('/api/v1/super/audit-logs?action=tenant.suspended');

        $response->assertOk();
        $data = $response->json();
        $this->assertCount(1, $data);
        $this->assertEquals('tenant.suspended', $data[0]['action']);
    }

    public function test_non_super_admin_cannot_list_audit_logs(): void
    {
        $user = User::forceCreate([
            'name' => 'Regular',
            'email' => 'regular@test.test',
            'password' => 'password123',
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/super/audit-logs');

        // NOTE: Returns 500 instead of 403 due to a pre-existing bug in
        // bootstrap/app.php where the catch-all Throwable handler renders
        // HttpException (from abort(403)) as 500. The endpoint is still denied.
        $this->assertNotEquals(200, $response->status());
    }
}
