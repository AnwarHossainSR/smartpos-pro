<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\PaymentMethod;
use App\Domain\Pos\ApprovalPolicy;
use App\Http\Controllers\Controller;
use App\Http\Resources\CashRegisterSessionResource;
use App\Http\Resources\TaxProfileResource;
use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Everything the POS terminal needs to open a register: the selling branch's
 * default warehouse, its cash register and any already-open session, the
 * jurisdiction it taxes under, and the payment methods it can tender — in one
 * request instead of the three round-trips the terminal made before.
 */
class PosController extends Controller
{
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('roles.permissions');
        $branch = Branch::with('business')->findOrFail($request->integer('branch_id') ?: $user->branch_id);
        $business = $branch->business;

        $warehouse = Warehouse::where('branch_id', $branch->id)->where('is_active', true)
            ->orderByDesc('is_default')->first()
            ?? Warehouse::where('business_id', $business->id)->where('is_active', true)->first();

        $register = CashRegister::where('branch_id', $branch->id)->where('is_active', true)->first();
        $session = $register?->openSession();

        return response()->json([
            'data' => [
                'branch' => ['id' => $branch->id, 'name' => $branch->name],
                'warehouse' => $warehouse ? ['id' => $warehouse->id, 'name' => $warehouse->name] : null,
                'cash_register' => $register ? ['id' => $register->id, 'name' => $register->name] : null,
                'session' => $session ? new CashRegisterSessionResource($session) : null,
                'tax_profile' => new TaxProfileResource($business->effectiveTaxProfile()),
                'price_display' => $business->price_display,
                // The terminal uses these to pre-empt the round trip and open the PIN
                // dialog itself — but the same limits are re-checked on POST /sales,
                // so a client that ignores them gains nothing.
                'approval_policy' => ApprovalPolicy::fromBusiness($business)->toArray(),
                'can_approve' => $user->hasPermission('pos.approve'),
                'currency' => $business->currency ?? config('pos.currency'),
                'payment_methods' => array_map(
                    static fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()],
                    PaymentMethod::cases(),
                ),
            ],
        ]);
    }
}
