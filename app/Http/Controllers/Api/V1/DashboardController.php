<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

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

        // Top products: last 7 days, sale_items join products, sum quantity + revenue, limit 5.
        $topProducts = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.status', 'completed')
            ->where('sales.sold_at', '>=', now()->subDays(7)->startOfDay())
            ->select('products.name', DB::raw('sum(sale_items.quantity) as quantity'), DB::raw('sum(sale_items.line_total) as revenue'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->limit(5)
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ]);

        // Recent sales: last 10 completed, newest first.
        $recentSales = Sale::query()
            ->where('status', 'completed')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->select('sales.id', 'sales.invoice_number', 'sales.total', 'sales.payment_status', 'sales.sold_at', 'customers.name as customer_name')
            ->orderByDesc('sales.sold_at')
            ->limit(10)
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'invoice_number' => $row->invoice_number,
                'total' => round((float) $row->total, 2),
                'payment_status' => $row->payment_status,
                'sold_at' => $row->sold_at,
                'customer_name' => $row->customer_name,
            ]);

        // Low stock items: 5 worst (largest shortfall ratio), product name, warehouse, qty, min_stock_level.
        $lowStockItems = InventoryBalance::query()
            ->join('products', 'products.id', '=', 'inventory_balances.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->whereColumn('inventory_balances.quantity', '<=', 'products.min_stock_level')
            ->select('products.name', 'warehouses.name as warehouse', 'inventory_balances.quantity', 'products.min_stock_level')
            ->orderByRaw('(products.min_stock_level - inventory_balances.quantity) desc')
            ->limit(5)
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'warehouse' => $row->warehouse,
                'quantity' => round((float) $row->quantity, 4),
                'min_stock_level' => round((float) $row->min_stock_level, 4),
            ]);

        // Week comparison: this-week revenue vs last-week revenue + pct change.
        $thisWeekStart = now()->startOfWeek();
        $thisWeekEnd = now()->endOfWeek();
        $lastWeekStart = now()->subWeek()->startOfWeek();
        $lastWeekEnd = now()->subWeek()->endOfWeek();

        $thisWeekRevenue = (float) Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$thisWeekStart, $thisWeekEnd])
            ->sum('total');

        $lastWeekRevenue = (float) Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$lastWeekStart, $lastWeekEnd])
            ->sum('total');

        $changePct = $lastWeekRevenue > 0
            ? round((($thisWeekRevenue - $lastWeekRevenue) / $lastWeekRevenue) * 100, 2)
            : 0;

        return $this->success([
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
            'top_products' => $topProducts,
            'recent_sales' => $recentSales,
            'low_stock_items' => $lowStockItems,
            'week_comparison' => [
                'this_week' => round($thisWeekRevenue, 2),
                'last_week' => round($lastWeekRevenue, 2),
                'change_pct' => $changePct,
            ],
        ]);
    }
}
