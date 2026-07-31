<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $units = Unit::query()
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('short_name', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate($this->perPage($request, 50));

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
