<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = QueryPipeline::for(Unit::query(), $request)
            ->search(['name', 'short_name'])
            ->sortable(['name', 'short_name'], default: 'name')
            ->paginate(50);

        return UnitResource::collection($units);
    }

    public function store(StoreUnitRequest $request): UnitResource
    {
        return new UnitResource(Unit::create($request->validated()));
    }

    public function update(StoreUnitRequest $request, Unit $unit): UnitResource
    {
        $unit->update($request->validated());

        return new UnitResource($unit);
    }

    public function destroy(Unit $unit)
    {
        $unit->delete();

        return response()->noContent();
    }
}
