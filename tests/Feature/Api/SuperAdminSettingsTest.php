<?php

namespace Tests\Feature\Api;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminSettingsTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_super_admin_can_list_settings(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->getJson('/api/v1/super/settings');

        $response->assertOk();
        $response->assertJsonStructure(['data' => ['modules', 'maintenance_mode']]);
    }

    public function test_super_admin_can_update_settings(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->putJson('/api/v1/super/settings', [
            'settings' => [
                'modules' => ['pos' => false, 'inventory' => true],
            ],
        ]);

        $response->assertOk();
        $this->assertFalse(PlatformSetting::get('modules')['pos']);
    }

    public function test_non_super_admin_cannot_access_settings(): void
    {
        $user = User::forceCreate([
            'name' => 'Regular',
            'email' => 'regular@test.test',
            'password' => 'password123',
            'status' => 'active',
            'is_super_admin' => false,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/super/settings');

        $this->assertNotEquals(200, $response->status());
    }
}
