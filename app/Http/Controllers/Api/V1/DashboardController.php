<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Headline KPIs + a simple sales trend for the dashboard. */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        $salesInRange = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$from, $to]);

        $trend = Sale::query()
            ->where('status', 'completed')
            ->where('sold_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('date(sold_at) as day, sum(total) as total')
            ->groupByRaw('date(sold_at)')
            ->orderBy('day')
            ->get();

        return response()->json([
            'data' => [
                'todays_sales' => round((float) (clone $salesInRange)->sum('total'), 2),
                'todays_transactions' => (clone $salesInRange)->count(),
                'total_due' => round((float) Sale::where('payment_status', '!=', 'paid')->sum('due_amount'), 2),
                'customer_dues' => round((float) Customer::where('balance', '>', 0)->sum('balance'), 2),
                'total_products' => Product::count(),
                'total_customers' => Customer::count(),
                'low_stock_count' => InventoryBalance::query()
                    ->whereColumn('quantity', '<=', DB::raw('(select min_stock_level from products where products.id = inventory_balances.product_id)'))
                    ->count(),
                'sales_trend' => $trend->map(fn ($row) => [
                    'day' => $row->day,
                    'total' => round((float) $row->total, 2),
                ]),
            ],
        ]);
    }
}
