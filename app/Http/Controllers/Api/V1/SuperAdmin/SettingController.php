<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdatePlatformSettingsRequest;
use App\Models\PlatformSetting;
use App\Services\Audit\AuditLogService;
use App\Services\Platform\ModuleRegistry;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettingController extends Controller
{
    use ApiResponse;

    public function __construct(
        private AuditLogService $audit,
        private ModuleRegistry $modules,
    ) {}

    public function index(): JsonResponse
    {
        $settings = PlatformSetting::all()->pluck('value', 'key');

        return $this->success($settings);
    }

    public function update(UpdatePlatformSettingsRequest $request): JsonResponse
    {
        $settings = $request->validated('settings');
        $before = PlatformSetting::whereIn('key', array_keys($settings))->pluck('value', 'key')->all();

        foreach ($settings as $key => $value) {
            PlatformSetting::set($key, $value);
        }

        $this->modules->flush();

        // Changing what every tenant can reach is exactly the kind of action the
        // platform audit trail exists for, and it was not recorded at all.
        $this->audit->log('platform.settings_updated', null, $before, $settings, $request->user());

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

        // Read-modify-write of a single blob holding *every* tenant's overrides, so two
        // admins editing different tenants at once would lose one of the two changes.
        // The lock makes the round trip atomic; moving overrides onto their own rows is
        // the real fix and is tracked separately.
        $overrides = DB::transaction(function () use ($request) {
            $row = PlatformSetting::where('key', 'tenant_overrides')->lockForUpdate()->first();
            $current = (array) ($row?->value ?? []);
            $current[$request->tenant_id] = $request->modules;

            PlatformSetting::set('tenant_overrides', $current);

            return $current;
        });

        $this->modules->flush();

        $this->audit->log(
            'platform.tenant_modules_updated',
            null,
            null,
            ['tenant_id' => $request->tenant_id, 'modules' => $request->modules],
            $request->user(),
            $request->tenant_id,
        );

        return $this->success($overrides);
    }
}
