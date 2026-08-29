<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Billing\StripeService;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StripeController extends Controller
{
    use ApiResponse;

    protected $stripe;

    public function __construct(StripeService $stripe)
    {
        $this->stripe = $stripe;
    }

    public function createCheckoutSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sale_id' => 'required|exists:sales,id',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $sale = Sale::with('customer')->find($request->sale_id);
        $sessionId = $this->stripe->createCheckoutSession($sale);

        return $this->success(['session_id' => $sessionId]);
    }

    public function createSubscriptionSession(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'price_id' => 'required|string',
            'return_url' => 'required|url',
        ]);

        if ($validator->fails()) {
            return $this->error($validator->errors()->first(), 422, $validator->errors()->toArray());
        }

        $sessionId = $this->stripe->createSubscriptionCheckout($request->price_id, $request->return_url);

        return $this->success(['session_id' => $sessionId]);
    }

    public function paymentStatus(Request $request, $token)
    {
        $sale = Sale::where('public_token', $token)->first();

        if (! $sale) {
            return $this->notFound('Sale not found');
        }

        return $this->success([
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_number,
            'due_amount' => $sale->due_amount,
            'payment_status' => $sale->payment_status,
            'total' => $sale->total,
            'paid_amount' => $sale->paid_amount,
        ]);
    }
}
