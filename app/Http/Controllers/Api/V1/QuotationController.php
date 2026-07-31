<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\ConvertQuotationRequest;
use App\Http\Requests\Sales\StoreQuotationRequest;
use App\Http\Requests\Sales\UpdateQuotationRequest;
use App\Models\Quotation;
use App\Services\Sales\QuotationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function __construct(protected QuotationService $service) {}

    public function index(Request $request): JsonResponse
    {
        $quotations = Quotation::with(['customer', 'user'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->search, fn ($q, $s) => $q->where('reference_number', 'like', "%{$s}%"))
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json($quotations);
    }

    public function store(StoreQuotationRequest $request): JsonResponse
    {
        $quotation = $this->service->create($request->validated(), $request->user());

        return response()->json(['data' => $quotation], 201);
    }

    public function show(Quotation $quotation): JsonResponse
    {
        return response()->json(['data' => $quotation->load(['items.product', 'customer', 'user', 'convertedToSale'])]);
    }

    public function update(UpdateQuotationRequest $request, Quotation $quotation): JsonResponse
    {
        $quotation->update($request->validated());

        return response()->json(['data' => $quotation]);
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $quotation->delete();

        return response()->json(null, 204);
    }

    public function convert(ConvertQuotationRequest $request, Quotation $quotation): JsonResponse
    {
        $sale = $this->service->convertToSale($quotation, $request->validated(), $request->user());

        return response()->json(['data' => $sale->load(['items.product', 'payments'])], 201);
    }
}
