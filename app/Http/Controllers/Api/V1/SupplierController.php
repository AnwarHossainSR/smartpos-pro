<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Support\Tenancy\TenantContext;
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

    public function show($id): SupplierResource
    {
        $supplier = app(TenantContext::class)->has()
            ? Supplier::findOrFail($id)
            : Supplier::withoutTenancy()->findOrFail($id);

        return new SupplierResource($supplier);
    }

    public function update(StoreSupplierRequest $request, $id): SupplierResource
    {
        $supplier = app(TenantContext::class)->has()
            ? Supplier::findOrFail($id)
            : Supplier::withoutTenancy()->findOrFail($id);

        $supplier->update($request->validated());

        return new SupplierResource($supplier);
    }

    public function destroy(Request $request, $id)
    {
        $supplier = app(TenantContext::class)->has()
            ? Supplier::findOrFail($id)
            : Supplier::withoutTenancy()->findOrFail($id);

        $supplier->delete();

        return response()->noContent();
    }
}
