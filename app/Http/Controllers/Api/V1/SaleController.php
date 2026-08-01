<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\SaleStatus;
use App\Domain\Sales\Data\CollectPaymentData;
use App\Domain\Sales\Data\CreateSaleData;
use App\Domain\Sales\Data\PaymentData;
use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ResumeSaleRequest;
use App\Http\Requests\Sales\StoreSalePaymentRequest;
use App\Http\Requests\Sales\StoreSaleRequest;
use App\Http\Requests\Sales\VoidSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use App\Services\Sales\SaleService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaleController extends Controller
{
    public function __construct(protected SaleService $sales) {}

    public function index(Request $request)
    {
        $sales = QueryPipeline::for(Sale::query()->with(['customer', 'cashier']), $request)
            ->search(['invoice_number'])
            ->allow(
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::enum('status', SaleStatus::class),
                AllowedFilter::enum('payment_status', PaymentStatus::class),
                AllowedFilter::dateRange('sold_at'),
            )
            ->sortable(['id', 'invoice_number', 'total', 'sold_at'], default: '-id')
            ->paginate();

        return SaleResource::collection($sales);
    }

    /** POS checkout. Idempotent when an Idempotency-Key header is supplied. */
    public function store(StoreSaleRequest $request): JsonResponse
    {
        try {
            $sale = $this->sales->create(CreateSaleData::fromArray($request->validated()), $request->user());
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
        return new SaleResource(
            $this->sales->collectPayment($sale, CollectPaymentData::fromArray($request->validated())),
        );
    }

    /** Finalises a held or draft cart into a completed sale. */
    public function resume(ResumeSaleRequest $request, Sale $sale): SaleResource
    {
        $payments = PaymentData::collection($request->validated()['payments'] ?? []);

        return new SaleResource($this->sales->resume($sale, $payments, $request->user()));
    }

    /** Cancels a completed sale and reverses the stock it moved. */
    public function void(VoidSaleRequest $request, Sale $sale): SaleResource
    {
        return new SaleResource($this->sales->void($sale, $request->user(), $request->validated()['reason'] ?? null));
    }

    /** Streams the invoice inline by default; `?download=1` forces a Content-Disposition download. */
    public function invoice(Request $request, Sale $sale)
    {
        $pdf = $this->sales->invoicePdf($sale);
        $filename = "{$sale->invoice_number}.pdf";

        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
