<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    use ApiResponse;

    public function __invoke(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;
        $globalModules = PlatformSetting::get('modules', []);
        $overrides = PlatformSetting::get('tenant_overrides', []);

        $enabled = [];
        foreach ($globalModules as $module => $allowed) {
            if (! $allowed) {
                continue;
            }
            if (isset($overrides[$tenantId][$module])) {
                $enabled[$module] = (bool) $overrides[$tenantId][$module];
            } else {
                $enabled[$module] = true;
            }
        }

        return $this->success($enabled);
    }
}
