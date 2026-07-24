<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read-only reporting endpoints. Every query is tenant-scoped by the global
 * TenantScope, so reports can never span businesses. All support a date range
 * (from/to) and, where relevant, a branch filter.
 */
class ReportController extends Controller
{
    /** Headline sales figures + payment-method breakdown for a period. */
    public function salesSummary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $base = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$from, $to])
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id));

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'invoices' => (clone $base)->count(),
                'gross_sales' => round((float) (clone $base)->sum('subtotal'), 2),
                'discounts' => round((float) (clone $base)->sum('discount_amount'), 2),
                'tax' => round((float) (clone $base)->sum('tax_amount'), 2),
                'net_sales' => round((float) (clone $base)->sum('total'), 2),
                'amount_paid' => round((float) (clone $base)->sum('paid_amount'), 2),
                'amount_due' => round((float) (clone $base)->sum('due_amount'), 2),
            ],
        ]);
    }

    /** Best-selling products by quantity and revenue. */
    public function salesByProduct(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($request->branch_id, fn ($q, $id) => $q->where('sales.branch_id', $id))
            ->selectRaw('sale_items.product_id, sale_items.name,
                sum(sale_items.quantity) as quantity,
                sum(sale_items.line_total) as revenue,
                sum(sale_items.unit_cost * sale_items.quantity) as cost')
            ->groupBy('sale_items.product_id', 'sale_items.name')
            ->orderByDesc('quantity')
            ->limit($request->integer('limit', 20))
            ->get()
            ->map(fn ($r) => [
                'product_id' => $r->product_id,
                'name' => $r->name,
                'quantity' => (float) $r->quantity,
                'revenue' => round((float) $r->revenue, 2),
                'profit' => round((float) $r->revenue - (float) $r->cost, 2),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** Gross profit = revenue - cost of goods sold, over the period. */
    public function profit(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $row = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($request->branch_id, fn ($q, $id) => $q->where('sales.branch_id', $id))
            ->selectRaw('sum(sale_items.line_total) as revenue,
                sum(sale_items.unit_cost * sale_items.quantity) as cost')
            ->first();

        $revenue = round((float) ($row->revenue ?? 0), 2);
        $cost = round((float) ($row->cost ?? 0), 2);

        return response()->json([
            'data' => [
                'revenue' => $revenue,
                'cost_of_goods_sold' => $cost,
                'gross_profit' => round($revenue - $cost, 2),
                'margin_percent' => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 2) : 0,
            ],
        ]);
    }

    /** Current stock valuation (quantity x average cost) per warehouse. */
    public function inventoryValuation(Request $request): JsonResponse
    {
        $rows = InventoryBalance::query()
            ->join('warehouses', 'warehouses.id', '=', 'inventory_balances.warehouse_id')
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('inventory_balances.warehouse_id', $id))
            ->selectRaw('warehouses.id as warehouse_id, warehouses.name as warehouse,
                sum(inventory_balances.quantity) as quantity,
                sum(inventory_balances.quantity * inventory_balances.average_cost) as value')
            ->groupBy('warehouses.id', 'warehouses.name')
            ->get()
            ->map(fn ($r) => [
                'warehouse_id' => $r->warehouse_id,
                'warehouse' => $r->warehouse,
                'quantity' => (float) $r->quantity,
                'value' => round((float) $r->value, 2),
            ]);

        return response()->json([
            'data' => [
                'total_value' => round((float) $rows->sum('value'), 2),
                'by_warehouse' => $rows,
            ],
        ]);
    }

    /** Products at or below their minimum stock level. */
    public function lowStock(Request $request): JsonResponse
    {
        $rows = Product::query()
            ->where('track_inventory', true)
            ->withSum('inventoryBalances as stock_quantity', 'quantity')
            ->havingRaw('coalesce(stock_quantity, 0) <= min_stock_level')
            ->orderByRaw('coalesce(stock_quantity, 0) asc')
            ->limit($request->integer('limit', 100))
            ->get(['id', 'name', 'sku', 'min_stock_level', 'reorder_quantity'])
            ->map(fn ($p) => [
                'product_id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'stock' => (float) ($p->stock_quantity ?? 0),
                'min_stock_level' => (float) $p->min_stock_level,
                'reorder_quantity' => (float) $p->reorder_quantity,
            ]);

        return response()->json(['data' => $rows]);
    }

    /** Outstanding receivables (customers) and payables (suppliers). */
    public function dues(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'customer_receivables' => round((float) Customer::where('balance', '>', 0)->sum('balance'), 2),
                'supplier_payables' => round((float) Supplier::where('balance', '>', 0)->sum('balance'), 2),
                'top_customer_dues' => Customer::where('balance', '>', 0)
                    ->orderByDesc('balance')->limit(10)->get(['id', 'name', 'balance'])
                    ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'balance' => (float) $c->balance]),
                'top_supplier_dues' => Supplier::where('balance', '>', 0)
                    ->orderByDesc('balance')->limit(10)->get(['id', 'name', 'balance'])
                    ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'balance' => (float) $s->balance]),
            ],
        ]);
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function range(Request $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        return [$from, $to];
    }
}
