<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\InventoryBalance;
use App\Models\Sale;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class WarehouseController extends Controller
{
    public function index(): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'branch_id', 'is_default']);

        return response()->json(['data' => $warehouses]);
    }

    public function show(Warehouse $warehouse): WarehouseResource
    {
        return new WarehouseResource($warehouse->load('branch'));
    }

    public function store(StoreWarehouseRequest $request): WarehouseResource
    {
        $data = $request->validated();
        $data['business_id'] = $request->user()->business_id;

        return new WarehouseResource(Warehouse::create($data)->load('branch'));
    }

    public function update(StoreWarehouseRequest $request, Warehouse $warehouse): WarehouseResource
    {
        $warehouse->update($request->validated());

        return new WarehouseResource($warehouse->load('branch'));
    }

    public function destroy(Warehouse $warehouse)
    {
        if ($warehouse->is_default) {
            throw ValidationException::withMessages(['warehouse' => 'The default warehouse cannot be deleted.']);
        }

        if (InventoryBalance::where('warehouse_id', $warehouse->id)->where('quantity', '!=', 0)->exists()) {
            throw ValidationException::withMessages(['warehouse' => 'This warehouse still holds stock and cannot be deleted.']);
        }

        if (Sale::where('warehouse_id', $warehouse->id)->exists()) {
            throw ValidationException::withMessages(['warehouse' => 'This warehouse has sales history and cannot be deleted.']);
        }

        $warehouse->delete();

        return response()->noContent();
    }
}
