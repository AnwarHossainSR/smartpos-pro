<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Analytics\ForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    protected ForecastService $forecast;

    public function __construct(ForecastService $forecast)
    {
        $this->forecast = $forecast;
    }

    public function index(Request $request): JsonResponse
    {
        $productId = $request->input('product_id');

        if ($productId) {
            $forecast = $this->forecast->forecastProduct($productId);

            return response()->json(['data' => [$forecast]]);
        }

        $productIds = $request->input('product_ids');
        if ($productIds) {
            $ids = array_map('intval', explode(',', $productIds));
            $forecasts = [];
            foreach ($ids as $id) {
                $forecasts = array_merge($forecasts, $this->forecast->forecastProduct($id));
            }

            return response()->json(['data' => $forecasts]);
        }

        $products = Product::where('is_active', true)
            ->whereNotNull('avg_daily_sales')
            ->take(10)
            ->get();

        $forecasts = [];
        foreach ($products as $product) {
            $forecast = $this->forecast->forecastProduct($product->id);
            $forecasts = array_merge($forecasts, [$forecast]);
        }

        return response()->json(['data' => $forecasts]);
    }
}
