<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\InventoryBalance;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Services\Accounting\FinancialStatementService;
use App\Services\Reporting\ZReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

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

        // One pass, seven aggregates — the previous shape cloned the query per figure
        // and scanned the same rows seven times over.
        $totals = Sale::query()
            ->where('status', 'completed')
            ->whereBetween('sold_at', [$from, $to])
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->selectRaw('count(*) as invoices,
                coalesce(sum(subtotal), 0) as gross_sales,
                coalesce(sum(discount_amount), 0) as discounts,
                coalesce(sum(tax_amount), 0) as tax,
                coalesce(sum(total), 0) as net_sales,
                coalesce(sum(paid_amount), 0) as amount_paid,
                coalesce(sum(due_amount), 0) as amount_due')
            // toBase() keeps the tenant + soft-delete scopes but returns a plain row:
            // these aggregates aren't Sale attributes and shouldn't be hydrated as one.
            ->toBase()
            ->first();

        return response()->json([
            'data' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'invoices' => (int) ($totals->invoices ?? 0),
                'gross_sales' => round((float) ($totals->gross_sales ?? 0), 2),
                'discounts' => round((float) ($totals->discounts ?? 0), 2),
                'tax' => round((float) ($totals->tax ?? 0), 2),
                'net_sales' => round((float) ($totals->net_sales ?? 0), 2),
                'amount_paid' => round((float) ($totals->amount_paid ?? 0), 2),
                'amount_due' => round((float) ($totals->amount_due ?? 0), 2),
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
            ->limit($this->limit($request, 20))
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
            ->limit($this->limit($request, 100))
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

    /** End-of-day Z-report: sales, tax, cash reconciliation, voids/returns. */
    public function zReport(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'branch_id' => 'nullable|string',
        ]);

        $business = $request->user()->business;
        $date = Carbon::parse($request->input('date'));
        $branchId = $request->input('branch_id');

        $report = app(ZReportService::class)->generate($business, $date, $branchId);

        return response()->json(['data' => $report]);
    }

    /** Thermal-friendly PDF of the Z-report. */
    public function zReportPdf(Request $request): Response
    {
        $request->validate([
            'date' => 'required|date',
            'branch_id' => 'nullable|string',
            'download' => 'nullable|boolean',
        ]);

        $business = $request->user()->business;
        $date = Carbon::parse($request->input('date'));
        $branchId = $request->input('branch_id');
        $download = $request->boolean('download', false);

        $pdf = app(ZReportService::class)->pdf($business, $date, $branchId);

        $filename = 'z-report-'.$date->format('Y-m-d').($branchId ? '-'.$branchId : '').'.pdf';

        return $download
            ? $pdf->download($filename)
            : $pdf->stream($filename);
    }

    public function profitLoss(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $service = app(FinancialStatementService::class);
        $data = $service->profitLoss($from->toDateString(), $to->toDateString());

        return response()->json(['data' => $data]);
    }

    public function balanceSheet(Request $request): JsonResponse
    {
        $request->validate([
            'as_of' => 'required|date',
        ]);

        $asOf = $request->date('as_of')?->toDateString();

        $service = app(FinancialStatementService::class);
        $data = $service->balanceSheet($asOf);

        return response()->json(['data' => $data]);
    }

    /**
     * Longest reportable window. Reports aggregate over every sale in the range, so an
     * unbounded `from` ("?from=1970-01-01") turns a throttled endpoint into a full-table
     * scan of a tenant's entire trading history on demand.
     */
    protected const MAX_RANGE_DAYS = 366;

    /** @return array{0: Carbon, 1: Carbon} */
    protected function range(Request $request): array
    {
        $from = $request->date('from')?->startOfDay() ?? now()->subDays(30)->startOfDay();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        if ($from->greaterThan($to)) {
            throw ValidationException::withMessages([
                'from' => ['The from date must not be after the to date.'],
            ]);
        }

        if ($from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages([
                'from' => ['The reporting range may not exceed '.self::MAX_RANGE_DAYS.' days.'],
            ]);
        }

        return [$from, $to];
    }
}
