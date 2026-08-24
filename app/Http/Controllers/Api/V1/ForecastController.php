<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ForecastRequest;
use App\Models\Product;
use App\Services\Analytics\ForecastService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * Demand forecasts behind the reorder suggestions. Reads only; the arithmetic
 * lives in {@see ForecastService}.
 */
class ForecastController extends Controller
{
    use ApiResponse;

    /** How many products the unfiltered call forecasts. */
    private const DEFAULT_LIMIT = 10;

    public function __construct(private readonly ForecastService $forecast) {}

    public function index(ForecastRequest $request): JsonResponse
    {
        $ids = $request->productIds();

        if ($ids === []) {
            // Product ids are UUIDs, so they can only come from a real lookup —
            // never from casting the query string to an integer.
            $ids = Product::query()
                ->where('is_active', true)
                ->whereNotNull('avg_daily_sales')
                ->take(self::DEFAULT_LIMIT)
                ->pluck('id')
                ->all();
        }

        $forecasts = array_map(
            fn (string $id): array => $this->forecast->forecastProduct($id),
            $ids,
        );

        return response()->json(['data' => $forecasts]);
    }
}
