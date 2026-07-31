<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Headline KPIs + a simple sales trend for the dashboard. */
    public function summary(Request $request): JsonResponse
    {
        $from = $request->date('from') ?? now()->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        // Both headline sale figures in one pass rather than a cloned query each.
        // toBase() keeps the tenant + soft-delete scopes but returns plain rows: these
        // aggregates aren't Sale attributes and shouldn't be hydrated as one.
        $salesInRange = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw('coalesce(sum(total), 0) as revenue, count(*) as transactions')
            ->toBase()
            ->first();

        $trend = Sale::query()
            ->where('status', 'completed')
            ->where('sold_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('date(sold_at) as day, coalesce(sum(total), 0) as total')
            ->groupByRaw('date(sold_at)')
            ->orderBy('day')
            ->toBase()
            ->get();

        return response()->json([
            'data' => [
                'todays_sales' => round((float) ($salesInRange->revenue ?? 0), 2),
                'todays_transactions' => (int) ($salesInRange->transactions ?? 0),
                'total_due' => round((float) Sale::where('payment_status', '!=', 'paid')->sum('due_amount'), 2),
                'customer_dues' => round((float) Customer::where('balance', '>', 0)->sum('balance'), 2),
                'total_products' => Product::count(),
                'total_customers' => Customer::count(),
                // A join, not a correlated subquery: the latter re-ran a lookup on
                // `products` for every balance row in the tenant.
                'low_stock_count' => InventoryBalance::query()
                    ->join('products', 'products.id', '=', 'inventory_balances.product_id')
                    ->whereColumn('inventory_balances.quantity', '<=', 'products.min_stock_level')
                    ->count(),
                'sales_trend' => $trend->map(fn ($row) => [
                    'day' => $row->day,
                    'total' => round((float) $row->total, 2),
                ]),
            ],
        ]);
    }
}
