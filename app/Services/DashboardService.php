<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getSummary(Request $request): array
    {
        $from = $request->date('from') ?? now()->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        $salesInRange = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw('coalesce(sum(total), 0) as revenue, count(*) as transactions')
            ->toBase()
            ->first();

        return [
            'todays_sales' => round((float) ($salesInRange->revenue ?? 0), 2),
            'todays_transactions' => (int) ($salesInRange->transactions ?? 0),
            'total_due' => round((float) Sale::where('payment_status', '!=', 'paid')->sum('due_amount'), 2),
            'customer_dues' => round((float) Customer::where('balance', '>', 0)->sum('balance'), 2),
            'total_products' => Product::count(),
            'total_customers' => Customer::count(),
            'low_stock_count' => InventoryBalance::query()
                ->join('products', 'products.id', '=', 'inventory_balances.product_id')
                ->whereColumn('inventory_balances.quantity', '<=', 'products.min_stock_level')
                ->count(),
            'sales_trend' => $this->getSalesTrend(),
            'top_products' => $this->getTopProducts(),
            'recent_sales' => $this->getRecentSales(),
            'low_stock_items' => $this->getLowStockItems(),
            'week_comparison' => $this->getWeekComparison(),
        ];
    }

    public function getSalesTrend(): array
    {
        return Sale::query()
            ->where('status', 'completed')
            ->where('sold_at', '>=', now()->subDays(13)->startOfDay())
            ->selectRaw('date(sold_at) as day, coalesce(sum(total), 0) as total')
            ->groupByRaw('date(sold_at)')
            ->orderBy('day')
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'day' => $row->day,
                'total' => round((float) $row->total, 2),
            ])
            ->toArray();
    }

    public function getTopProducts(): array
    {
        return SaleItem::query()
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
            ])
            ->toArray();
    }

    public function getRecentSales(): array
    {
        return Sale::query()
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
            ])
            ->toArray();
    }

    public function getLowStockItems(): array
    {
        return InventoryBalance::query()
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
            ])
            ->toArray();
    }

    public function getWeekComparison(): array
    {
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

        return [
            'this_week' => round($thisWeekRevenue, 2),
            'last_week' => round($lastWeekRevenue, 2),
            'change_pct' => $changePct,
        ];
    }
}
