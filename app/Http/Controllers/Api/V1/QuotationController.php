<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'customer_id' => 'nullable|integer|exists:customers,id',
            'note' => 'nullable|string|max:2000',
            'terms' => 'nullable|string|max:2000',
            'valid_until' => 'nullable|date|after:today',
            'status' => 'nullable|in:draft,sent',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'nullable|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
        ]);

        $quotation = $this->service->create($validated, $request->user());

        return response()->json(['data' => $quotation], 201);
    }

    public function show(Quotation $quotation): JsonResponse
    {
        return response()->json(['data' => $quotation->load(['items.product', 'customer', 'user', 'convertedToSale'])]);
    }

    public function update(Request $request, Quotation $quotation): JsonResponse
    {
        $request->validate(['status' => 'sometimes|in:draft,sent,accepted,rejected']);
        $quotation->update($request->only(['status', 'note', 'terms', 'valid_until']));

        return response()->json(['data' => $quotation]);
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $quotation->delete();

        return response()->json(null, 204);
    }

    public function convert(Request $request, Quotation $quotation): JsonResponse
    {
        $validated = $request->validate([
            'warehouse_id' => 'required|integer|exists:warehouses,id',
            'payments' => 'nullable|array',
            'payments.*.method' => 'required|string',
            'payments.*.amount' => 'required|numeric|min:0',
        ]);

        $sale = $this->service->convertToSale($quotation, $validated, $request->user());

        return response()->json(['data' => $sale->load(['items.product', 'payments'])], 201);
    }
}
