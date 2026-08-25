<?php

namespace Tests\Feature\Api;

use App\Models\BackupLog;
use App\Models\User;
use App\Support\Rbac\RbacProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminBackupTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        app(RbacProvisioner::class)->syncPermissions();
        $this->superAdmin = User::forceCreate([
            'name' => 'Super Admin',
            'email' => 'super-backup-test@example.test',
            'password' => 'password123',
            'status' => 'active',
            'is_super_admin' => true,
        ]);
    }

    public function test_super_admin_can_list_backups(): void
    {
        BackupLog::factory()->count(3)->create(['created_by' => $this->superAdmin->id]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/super/backups');

        $response->assertOk();
        $this->assertCount(3, $response->json());
    }

    public function test_non_super_admin_cannot_list_backups(): void
    {
        $user = User::forceCreate([
            'name' => 'Regular User',
            'email' => 'regular@example.test',
            'password' => 'password123',
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/super/backups');

        $response->assertForbidden();
    }

    public function test_super_admin_can_get_backup_details(): void
    {
        $backup = BackupLog::factory()->create(['created_by' => $this->superAdmin->id]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/super/backups/'.$backup->id.'/download');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['url', 'filename', 'expires_at']]);
    }

    public function test_download_pending_backup_returns_error(): void
    {
        $backup = BackupLog::factory()->pending()->create(['created_by' => $this->superAdmin->id]);

        $response = $this->actingAs($this->superAdmin)
            ->getJson('/api/v1/super/backups/'.$backup->id.'/download');

        $response->assertStatus(422);
    }

    public function test_super_admin_can_delete_backup(): void
    {
        $backup = BackupLog::factory()->create(['created_by' => $this->superAdmin->id]);

        $response = $this->actingAs($this->superAdmin)
            ->deleteJson('/api/v1/super/backups/'.$backup->id);

        $response->assertOk();
        $this->assertDatabaseMissing('backup_logs', ['id' => $backup->id]);
    }
}
