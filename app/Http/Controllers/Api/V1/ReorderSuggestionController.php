<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\PurchaseStatus;
use App\Domain\Purchasing\Data\CreatePurchaseData;
use App\Domain\Purchasing\Data\PurchaseLineData;
use App\Http\Controllers\Controller;
use App\Http\Resources\PurchaseResource;
use App\Models\Warehouse;
use App\Services\Inventory\ReorderSuggestionService;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReorderSuggestionController extends Controller
{
    public function __construct(
        protected ReorderSuggestionService $suggestions,
        protected PurchaseService $purchases,
    ) {}

    public function index()
    {
        return response()->json([
            'data' => $this->suggestions->getSuggestions(),
        ]);
    }

    public function createDraftPo(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_cost' => 'required|numeric|min:0',
            'items.*.supplier_id' => 'required|string',
            'warehouse_id' => 'required|string',
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

        $grouped = collect($validated['items'])->groupBy('supplier_id');

        $purchases = collect();

        foreach ($grouped as $supplierId => $items) {
            $lineItems = $items->map(fn ($item) => [
                'product_id' => $item['product_id'],
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
            ])->toArray();

            $data = new CreatePurchaseData(
                warehouseId: $warehouse->id,
                supplierId: $supplierId,
                items: PurchaseLineData::collection($lineItems),
                status: PurchaseStatus::Draft,
            );

            $purchase = $this->purchases->create($data, $request->user());
            $purchases->push($purchase);
        }

        return response()->json([
            'data' => PurchaseResource::collection($purchases),
            'message' => 'Draft purchase orders created',
        ])->setStatusCode(201);
    }
}
