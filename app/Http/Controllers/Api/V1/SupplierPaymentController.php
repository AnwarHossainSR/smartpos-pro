<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierPaymentRequest;
use App\Models\Supplier;
use App\Support\Tenancy\TenantContext;
use App\Services\Purchasing\SupplierPaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentController extends Controller
{
    public function store(StoreSupplierPaymentRequest $request, Supplier $supplier): JsonResource
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

    public function index(Supplier $supplier, Request $request): JsonResource
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