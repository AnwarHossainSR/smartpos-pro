<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\PurchaseStatus;
use App\Domain\Purchasing\Data\CreatePurchaseData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\ReceivePurchaseRequest;
use App\Http\Requests\Purchasing\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\Purchasing\PurchaseService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $purchases) {}

    public function index(Request $request)
    {
        $purchases = QueryPipeline::for(Purchase::query()->with('supplier'), $request)
            ->search(['reference_number'])
            ->allow(
                AllowedFilter::exact('supplier_id'),
                AllowedFilter::enum('status', PurchaseStatus::class),
                AllowedFilter::enum('payment_status', PaymentStatus::class),
                AllowedFilter::dateRange('purchased_at'),
            )
            ->sortable(['id', 'reference_number', 'total', 'purchased_at'], default: '-id')
            ->paginate();

        return PurchaseResource::collection($purchases);
    }

    public function store(StorePurchaseRequest $request): JsonResponse
    {
        $purchase = $this->purchases->create(CreatePurchaseData::fromArray($request->validated()), $request->user());

        return (new PurchaseResource($purchase))->response()->setStatusCode(201);
    }

    public function show(Purchase $purchase): PurchaseResource
    {
        return new PurchaseResource($purchase->load('items', 'supplier'));
    }

    public function approve(Purchase $purchase): JsonResponse
    {
        try {
            $purchase = $this->purchases->approve($purchase, request()->user());

            return (new PurchaseResource($purchase))->response()->setStatusCode(200);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }
    }

    public function receive(Purchase $purchase, ReceivePurchaseRequest $request): JsonResponse
    {
        try {
            $purchase = $this->purchases->receive($purchase, $request->validated('items'), $request->user());

            return (new PurchaseResource($purchase))->response()->setStatusCode(200);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['items' => $e->getMessage()]);
        }
    }

    public function cancel(Purchase $purchase): JsonResponse
    {
        try {
            $purchase = $this->purchases->cancel($purchase);

            return (new PurchaseResource($purchase))->response()->setStatusCode(200);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }
    }

    public function edit(Request $request, Purchase $purchase): JsonResponse
    {
        try {
            $purchase = $this->purchases->edit($purchase, $request->all());

            return (new PurchaseResource($purchase))->response()->setStatusCode(200);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }
    }

    public function destroy(Purchase $purchase): JsonResponse
    {
        try {
            $purchase = $this->purchases->cancel($purchase);

            return (new PurchaseResource($purchase))->response()->setStatusCode(200);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }
    }

    public function return(Request $request, Purchase $purchase): JsonResponse
    {
        try {
            $purchase = $this->purchases->returnPurchase($purchase, $request->all());

            return (new PurchaseResource($purchase))->response()->setStatusCode(200);
        } catch (\DomainException $e) {
            throw ValidationException::withMessages(['status' => $e->getMessage()]);
        }
    }
}
