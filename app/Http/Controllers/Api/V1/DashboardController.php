<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    use ApiResponse;

    public function __construct(
        private DashboardService $dashboard
    ) {}

    /** Headline KPIs + a simple sales trend for the dashboard. */
    public function summary(Request $request): JsonResponse
    {
        return $this->success($this->dashboard->getSummary($request));
    }
}
