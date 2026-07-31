<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\Purchasing\PurchaseService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
        $purchase = $this->purchases->create($request->validated(), $request->user());

        return (new PurchaseResource($purchase))->response()->setStatusCode(201);
    }

    public function show(Purchase $purchase): PurchaseResource
    {
        return new PurchaseResource($purchase->load('items', 'supplier'));
    }
}
