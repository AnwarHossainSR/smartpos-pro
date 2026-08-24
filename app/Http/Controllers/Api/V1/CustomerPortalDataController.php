<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Billing\StripeService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerPortalDataController extends Controller
{
    use ApiResponse;

    public function me(Request $request): JsonResponse
    {
        $customer = $request->user();

        return $this->success([
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
        ]);
    }

    public function invoices(Request $request): JsonResponse
    {
        $customer = $request->user();

        $sales = Sale::withoutTenancy()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->orderByDesc('sold_at')
            ->get()
            ->map(fn (Sale $sale) => [
                'id' => $sale->id,
                'invoice_number' => $sale->invoice_number,
                'total' => (float) $sale->total,
                'paid_amount' => (float) $sale->paid_amount,
                'due_amount' => (float) $sale->due_amount,
                'payment_status' => $sale->payment_status->value,
                'sold_at' => $sale->sold_at,
            ]);

        return $this->success($sales);
    }

    public function invoiceDetail(Request $request, string $sale): JsonResponse
    {
        $customer = $request->user();

        $saleModel = Sale::withoutTenancy()->find($sale);

        abort_if(! $saleModel, 404);
        abort_if($saleModel->customer_id !== $customer->id, 403);
        abort_if($saleModel->status !== SaleStatus::Completed, 404);

        $saleModel->load('items');

        return $this->success([
            'id' => $saleModel->id,
            'invoice_number' => $saleModel->invoice_number,
            'total' => (float) $saleModel->total,
            'paid_amount' => (float) $saleModel->paid_amount,
            'due_amount' => (float) $saleModel->due_amount,
            'payment_status' => $saleModel->payment_status->value,
            'sold_at' => $saleModel->sold_at,
            'items' => $saleModel->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'discount_amount' => (float) $item->discount_amount,
                'tax_amount' => (float) $item->tax_amount,
                'line_total' => (float) $item->line_total,
            ]),
        ]);
    }

    public function pay(Request $request, string $sale): JsonResponse
    {
        $customer = $request->user();

        $saleModel = Sale::withoutTenancy()->find($sale);

        abort_if(! $saleModel, 404);
        abort_if($saleModel->customer_id !== $customer->id, 403);
        abort_if($saleModel->status !== SaleStatus::Completed, 404);
        abort_if($saleModel->due_amount <= 0, 422, 'This invoice has no outstanding balance.');

        $stripe = app(StripeService::class);
        $sessionId = $stripe->createCheckoutSession($saleModel);

        return response()->json(['session_id' => $sessionId]);
    }
}
