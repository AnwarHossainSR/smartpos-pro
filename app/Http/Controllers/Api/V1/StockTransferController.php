<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ReceiveStockTransferRequest;
use App\Http\Requests\Inventory\StoreStockTransferRequest;
use App\Models\StockTransfer;
use App\Services\Inventory\StockTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockTransferController extends Controller
{
    public function __construct(protected StockTransferService $service) {}

    public function index(Request $request): JsonResponse
    {
        $transfers = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'requestedBy'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate($this->perPage($request, 20));

        return response()->json($transfers);
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $transfer = $this->service->request($request->validated(), $request->user());

        return response()->json(['data' => $transfer], 201);
    }

    public function show(StockTransfer $stockTransfer): JsonResponse
    {
        return response()->json(['data' => $stockTransfer->load(['items.product', 'fromWarehouse', 'toWarehouse', 'requestedBy', 'approvedBy', 'receivedBy'])]);
    }

    public function approve(StockTransfer $stockTransfer, Request $request): JsonResponse
    {
        $transfer = $this->service->approve($stockTransfer, $request->user());

        return response()->json(['data' => $transfer]);
    }

    public function receive(StockTransfer $stockTransfer, ReceiveStockTransferRequest $request): JsonResponse
    {
        $transfer = $this->service->receive($stockTransfer, $request->validated(), $request->user());

        return response()->json(['data' => $transfer]);
    }

    public function cancel(StockTransfer $stockTransfer, Request $request): JsonResponse
    {
        $transfer = $this->service->cancel($stockTransfer, $request->user());

        return response()->json(['data' => $transfer]);
    }
}
