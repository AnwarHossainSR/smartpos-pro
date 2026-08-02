<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\StoreCouponRequest;
use App\Models\Coupon;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $coupons = QueryPipeline::for(Coupon::query(), $request)
            ->search(['code'])
            ->allow(AllowedFilter::boolean('is_active'))
            ->sortable(['id', 'code', 'expires_at'], default: '-id')
            ->paginate();

        return response()->json($coupons);
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] = strtoupper($data['code']);

        $coupon = Coupon::create($data);

        return response()->json(['data' => $coupon], 201);
    }

    public function update(StoreCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->validated());

        return response()->json(['data' => $coupon]);
    }

    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();

        return response()->json(status: 204);
    }

    /** Validate a coupon code against a cart total and return the computed discount. */
    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
            'cart_total' => ['required', 'numeric', 'min:0'],
        ]);

        $coupon = Coupon::where('code', strtoupper($data['code']))->first();

        if (! $coupon) {
            return response()->json(['valid' => false, 'reason' => 'Coupon not found.'], 404);
        }

        $result = $coupon->evaluate((float) $data['cart_total']);

        return response()->json($result, $result['valid'] ? 200 : 422);
    }
}
