<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockBatchRequest;
use App\Http\Requests\Inventory\StoreStockSerialRequest;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockSerial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages product variants, stock batches, and serial numbers.
 */
class ProductVariantController extends Controller
{
    // ---------- Variants ----------

    public function variants(Product $product): JsonResponse
    {
        return response()->json(['data' => $product->variants]);
    }

    // ---------- Batches ----------

    public function batches(Request $request): JsonResponse
    {
        $batches = StockBatch::with(['product', 'warehouse'])
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->expiring_before, fn ($q, $d) => $q->where('expires_at', '<=', $d))
            ->where('quantity', '>', 0)
            ->orderBy('expires_at')
            ->paginate($this->perPage($request, 50));

        return response()->json($batches);
    }

    public function storeBatch(StoreStockBatchRequest $request): JsonResponse
    {
        $batch = StockBatch::create(array_merge($request->validated(), [
            'tenant_id' => $request->user()->tenant_id,
        ]));

        return response()->json(['data' => $batch], 201);
    }

    // ---------- Serials ----------

    public function serials(Request $request): JsonResponse
    {
        $serials = StockSerial::with(['product', 'warehouse'])
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->serial_number, fn ($q, $sn) => $q->where('serial_number', 'like', "%{$sn}%"))
            ->latest()
            ->paginate($this->perPage($request, 50));

        return response()->json($serials);
    }

    public function storeSerial(StoreStockSerialRequest $request): JsonResponse
    {
        $serial = StockSerial::create(array_merge($request->validated(), [
            'tenant_id' => $request->user()->tenant_id,
            'status' => SerialStatus::InStock,
        ]));

        return response()->json(['data' => $serial], 201);
    }
}
