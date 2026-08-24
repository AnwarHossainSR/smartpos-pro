<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\SerialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreStockBatchRequest;
use App\Http\Requests\Inventory\StoreStockSerialRequest;
use App\Models\Product;
use App\Models\StockBatch;
use App\Models\StockSerial;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages product variants, stock batches, and serial numbers.
 */
class ProductVariantController extends Controller
{
    use ApiResponse;
    // ---------- Variants ----------

    public function variants(Product $product): JsonResponse
    {
        return response()->json(['data' => $product->variants]);
    }

    // ---------- Batches ----------

    public function batches(Request $request): JsonResponse
    {
        $batches = QueryPipeline::for(
            StockBatch::with(['product', 'warehouse'])->where('quantity', '>', 0),
            $request,
        )
            ->allow(
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::callback('expiring_before', fn (Builder $q, $date) => $q->where('expires_at', '<=', $date)),
            )
            ->sortable(['expires_at', 'quantity'], default: 'expires_at')
            ->paginate(50);

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
        $serials = QueryPipeline::for(StockSerial::with(['product', 'warehouse']), $request)
            ->allow(
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::enum('status', SerialStatus::class),
                AllowedFilter::partial('serial_number'),
            )
            ->sortable(['id', 'serial_number'], default: '-id')
            ->paginate(50);

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
