<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
            ->paginate($request->integer('per_page', 20));

        return response()->json($transfers);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_warehouse_id' => 'required|integer|exists:warehouses,id',
            'to_warehouse_id' => 'required|integer|exists:warehouses,id',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.variant_id' => 'nullable|integer|exists:product_variants,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        $transfer = $this->service->request($validated, $request->user());

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

    public function receive(StockTransfer $stockTransfer, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'sometimes|array',
            'items.*.transfer_item_id' => 'required|integer',
            'items.*.quantity_received' => 'required|numeric|min:0',
        ]);

        $transfer = $this->service->receive($stockTransfer, $validated, $request->user());

        return response()->json(['data' => $transfer]);
    }

    public function cancel(StockTransfer $stockTransfer, Request $request): JsonResponse
    {
        $transfer = $this->service->cancel($stockTransfer, $request->user());

        return response()->json(['data' => $transfer]);
    }
}
