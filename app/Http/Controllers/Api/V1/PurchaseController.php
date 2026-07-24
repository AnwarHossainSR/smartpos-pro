<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StorePurchaseRequest;
use App\Http\Resources\PurchaseResource;
use App\Models\Purchase;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $purchases) {}

    public function index(Request $request)
    {
        $purchases = Purchase::query()
            ->with('supplier')
            ->when($request->search, fn ($q, $s) => $q->where('reference_number', 'like', "%{$s}%"))
            ->when($request->supplier_id, fn ($q, $id) => $q->where('supplier_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_status, fn ($q, $s) => $q->where('payment_status', $s))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

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
