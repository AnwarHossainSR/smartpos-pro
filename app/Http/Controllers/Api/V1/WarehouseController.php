<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreWarehouseRequest;
use App\Http\Resources\WarehouseResource;
use App\Models\InventoryBalance;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class WarehouseController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        // Page size is generous because this list doubles as the warehouse picker on
        // POS, purchases and transfers, where a second page would be a surprise.
        $warehouses = QueryPipeline::for(Warehouse::query()->with('branch')->orderByDesc('is_default'), $request)
            ->search(['name', 'code'])
            ->allow(
                AllowedFilter::boolean('is_active'),
                AllowedFilter::exact('branch_id'),
            )
            ->sortable(['name', 'code', 'created_at'], default: 'name')
            ->paginate(100);

        return WarehouseResource::collection($warehouses);
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
