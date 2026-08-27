<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdatePlatformSettingsRequest;
use App\Models\PlatformSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $settings = PlatformSetting::all()->pluck('value', 'key');

        return $this->success($settings);
    }

    public function update(UpdatePlatformSettingsRequest $request): JsonResponse
    {
        $settings = $request->validated('settings');

        foreach ($settings as $key => $value) {
            PlatformSetting::set($key, $value);
        }

        return $this->success(PlatformSetting::all()->pluck('value', 'key'));
    }

    public function tenantOverrides(): JsonResponse
    {
        $overrides = PlatformSetting::get('tenant_overrides', []);

        return $this->success($overrides);
    }

    public function updateTenantOverrides(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'modules' => 'required|array',
            'modules.*' => 'boolean',
        ]);

        $overrides = PlatformSetting::get('tenant_overrides', []);
        $overrides[$request->tenant_id] = $request->modules;
        PlatformSetting::set('tenant_overrides', $overrides);

        return $this->success($overrides);
    }

    public function isModuleEnabled(string $module, ?string $tenantId = null): bool
    {
        if ($tenantId) {
            $overrides = PlatformSetting::get('tenant_overrides', []);
            if (isset($overrides[$tenantId][$module])) {
                return (bool) $overrides[$tenantId][$module];
            }
        }

        $modules = PlatformSetting::get('modules', []);

        return (bool) ($modules[$module] ?? false);
    }
}
