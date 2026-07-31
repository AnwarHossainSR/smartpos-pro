<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreSalePaymentRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(protected SaleService $sales) {}

    public function index(Request $request)
    {
        $sales = Sale::query()
            ->with(['customer', 'cashier'])
            ->when($request->search, fn ($q, $s) => $q->where('invoice_number', 'like', "%{$s}%"))
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->customer_id, fn ($q, $id) => $q->where('customer_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->payment_status, fn ($q, $s) => $q->where('payment_status', $s))
            ->when($request->from, fn ($q, $d) => $q->whereDate('sold_at', '>=', $d))
            ->when($request->to, fn ($q, $d) => $q->whereDate('sold_at', '<=', $d))
            ->latest('id')
            ->paginate($request->integer('per_page', 25));

        return SaleResource::collection($sales);
    }

    /** POS checkout. Idempotent when an Idempotency-Key header is supplied. */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->sales->create($request->validated(), $request->user());
        } catch (InsufficientStockException $e) {
            return response()->json([
                'message' => 'One or more items do not have enough stock.',
                'error' => [
                    'product_id' => $e->productId,
                    'warehouse_id' => $e->warehouseId,
                    'available' => $e->available,
                    'requested' => $e->requested,
                ],
            ], 422);
        }

        return (new SaleResource($sale))->response()->setStatusCode(201);
    }

    public function show(Sale $sale): SaleResource
    {
        return new SaleResource($sale->load('items', 'payments', 'customer', 'cashier', 'coupon'));
    }

    /** Records a settlement payment against a due/partial sale. Idempotent when an Idempotency-Key header is supplied. */
    public function addPayment(StoreSalePaymentRequest $request, Sale $sale): SaleResource
    {
        return new SaleResource($this->sales->collectPayment($sale, $request->validated()));
    }

    /** Streams the invoice inline by default; `?download=1` forces a Content-Disposition download. */
    public function invoice(Request $request, Sale $sale)
    {
        $pdf = $this->sales->invoicePdf($sale);
        $filename = "{$sale->invoice_number}.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
