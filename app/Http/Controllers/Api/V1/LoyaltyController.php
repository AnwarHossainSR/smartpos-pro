<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\LoyaltyTransaction;
use App\Services\Loyalty\LoyaltyService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    use ApiResponse;

    public function __construct(protected LoyaltyService $loyalty) {}

    /** Point balance + recent history for a customer. */
    public function show(Request $request, Customer $customer): JsonResponse
    {
        $history = LoyaltyTransaction::where('customer_id', $customer->id)
            ->latest('id')->limit(50)->get(['type', 'points', 'balance_after', 'note', 'sale_id', 'created_at']);

        return $this->success([
            'customer_id' => $customer->id,
            'points' => (int) $customer->loyalty_points,
            'history' => $history,
        ]);
    }

    public function redeem(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $txn = $this->loyalty->redeem($customer, (int) $data['points'], $request->user()->id, $data['note'] ?? null);

        return $this->success(['points' => (int) $txn->points, 'balance' => (int) $txn->balance_after]);
    }

    public function adjust(Request $request, Customer $customer): JsonResponse
    {
        $data = $request->validate([
            'points' => ['required', 'integer', 'not_in:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $txn = $this->loyalty->adjust($customer, (int) $data['points'], $request->user()->id, $data['note'] ?? null);

        return $this->success(['points' => (int) $txn->points, 'balance' => (int) $txn->balance_after]);
    }
}
