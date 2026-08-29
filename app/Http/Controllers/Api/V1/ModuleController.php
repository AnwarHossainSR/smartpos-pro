<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Platform\ModuleRegistry;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleController extends Controller
{
    use ApiResponse;

    public function __construct(private ModuleRegistry $modules) {}

    public function __invoke(Request $request): JsonResponse
    {
        return $this->success($this->modules->forTenant($request->user()->tenant_id));
    }
}
