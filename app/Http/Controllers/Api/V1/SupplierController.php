<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = QueryPipeline::for(Supplier::query(), $request)
            ->search(['name', 'company', 'phone'])
            ->allow(
                AllowedFilter::flag('has_due', fn (Builder $q) => $q->where('balance', '>', 0)),
            )
            ->sortable(['name', 'company', 'phone', 'balance', 'created_at'], default: 'name')
            ->paginate();

        return SupplierResource::collection($suppliers);
    }

    public function store(StoreSupplierRequest $request): SupplierResource
    {
        $data = $request->validated();
        $data['balance'] = $data['opening_balance'] ?? 0;

        return new SupplierResource(Supplier::create($data));
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $supplier->update($request->validated());

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->noContent();
    }
}
