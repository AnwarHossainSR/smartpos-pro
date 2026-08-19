<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierPaymentRequest;
use App\Http\Requests\Purchasing\StoreSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Services\Purchasing\SupplierPaymentService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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

    public function storePayment(StoreSupplierPaymentRequest $request, Supplier $supplier): JsonResource
    {
        $data = $request->validated();

        $supplier = app(TenantContext::class)->has()
            ? Supplier::findOrFail($supplier->id)
            : Supplier::withoutTenancy()->findOrFail($supplier->id);

        $service = app(SupplierPaymentService::class);
        $payment = $service->recordPayment(
            $supplier,
            (float) $data['amount'],
            $data['method'],
            $data['reference'] ?? null,
            $data['paid_at'] ?? null
        );

        return new JsonResource($payment);
    }

    public function indexPayments(Supplier $supplier, Request $request): JsonResource
    {
        $supplier = app(TenantContext::class)->has()
            ? Supplier::findOrFail($supplier->id)
            : Supplier::withoutTenancy()->findOrFail($supplier->id);

        $service = app(SupplierPaymentService::class);
        $payments = $service->listPayments($supplier, $request->all());

        return new JsonResource($payments);
    }

    public function statement(Supplier $supplier): JsonResource
    {
        $supplier = app(TenantContext::class)->has()
            ? Supplier::findOrFail($supplier->id)
            : Supplier::withoutTenancy()->findOrFail($supplier->id);

        $service = app(SupplierPaymentService::class);
        $statement = $service->statement($supplier);

        return new JsonResource($statement);
    }
}
