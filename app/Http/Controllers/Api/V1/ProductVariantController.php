<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
        $variants = $product->variants()->with('attributes.attribute')->get();

        return response()->json(['data' => $variants]);
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
            ->paginate($request->integer('per_page', 50));

        return response()->json($batches);
    }

    public function storeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'batch_number' => 'required|string|max:100',
            'quantity' => 'required|numeric|min:0.0001',
            'unit_cost' => 'nullable|numeric|min:0',
            'manufactured_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        $batch = StockBatch::create(array_merge($validated, [
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
            ->paginate($request->integer('per_page', 50));

        return response()->json($serials);
    }

    public function storeSerial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'serial_number' => 'required|string|max:200',
        ]);

        $serial = StockSerial::create(array_merge($validated, [
            'tenant_id' => $request->user()->tenant_id,
            'status' => 'in_stock',
        ]));

        return response()->json(['data' => $serial], 201);
    }
}
