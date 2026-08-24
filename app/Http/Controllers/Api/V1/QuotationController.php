<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\QuotationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ConvertQuotationRequest;
use App\Http\Requests\Sales\StoreQuotationRequest;
use App\Http\Requests\Sales\UpdateQuotationRequest;
use App\Models\Quotation;
use App\Services\Sales\QuotationService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    use ApiResponse;

    public function __construct(protected QuotationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $quotations = QueryPipeline::for(Quotation::with(['customer', 'user']), $request)
            ->search(['reference_number'])
            ->allow(
                AllowedFilter::enum('status', QuotationStatus::class),
                AllowedFilter::exact('customer_id'),
                AllowedFilter::dateRange('valid_until'),
            )
            ->sortable(['id', 'valid_until', 'total'], default: '-id')
            ->paginate(20);

        return $this->success($quotations);
    }

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $quotation = $this->service->create($request->validated(), $request->user());

        return $this->created($quotation);
    }

    public function show(Quotation $quotation): JsonResponse
    {
        return $this->success($quotation->load(['items.product', 'customer', 'user', 'convertedToSale']));
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): JsonResponse
    {
        $quotation->update($request->validated());

        return $this->success($quotation);
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $quotation->delete();

        return $this->noContent();
    }

    public function convert(ConvertQuotationRequest $request, Quotation $quotation): JsonResponse
    {
        $sale = $this->service->convertToSale($quotation, $request->validated(), $request->user());

        return $this->created($sale->load(['items.product', 'payments']));
    }
}
