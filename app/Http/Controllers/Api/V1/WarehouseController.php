<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;

class WarehouseController extends Controller
{
    public function index(): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'is_default']);

        return response()->json(['data' => $warehouses]);
    }
}
