<?php

namespace App\Services\Reporting;

use App\Domain\Enums\PaymentMethod;
use App\Domain\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashRegisterSession;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemTax;
use App\Models\SalePayment;
use App\Models\SaleReturn;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The end-of-day close: one figure set covering money taken, tax charged and
 * cash counted for a single trading day, optionally narrowed to one branch.
 *
 * Everything is read from what was *recorded at the time* — payment rows, the
 * snapshotted `sale_item_taxes` breakdown, the session's own opening/expected
 * columns — never recomputed from live configuration. A rate change or a
 * renamed tax tomorrow must not rewrite yesterday's close.
 *
 * A day with no trading is a real answer, not an error: every group comes back
 * as an empty list and every total as zero.
 */
class ZReportService
{
    /** @return array<string, mixed> */
    public function generate(Business $business, Carbon $date, ?string $branchId = null): array
    {
        $from = $date->copy()->startOfDay();
        $to = $date->copy()->endOfDay();

        $totals = $this->saleTotals($from, $to, $branchId);
        $transactions = (int) ($totals->transactions ?? 0);
        $netSales = $this->money($totals->net_sales ?? 0);

        $lineDiscounts = $this->lineDiscounts($from, $to, $branchId);
        $saleDiscounts = $this->money($totals->sale_discounts ?? 0);
        $couponDiscounts = $this->money($totals->coupon_discounts ?? 0);

        $taxes = $this->taxComponents($from, $to, $branchId);
        $cash = $this->cashSessions($from, $to, $branchId);
        $branch = $branchId ? Branch::find($branchId) : null;

        return [
            'date' => $from->toDateString(),
            'generated_at' => Carbon::now()->toIso8601String(),
            'currency' => $business->currency ?? config('pos.currency', 'USD'),
            'business' => ['id' => $business->id, 'name' => $business->name],
            'branch' => $branch ? ['id' => $branch->id, 'name' => $branch->name] : null,
            'totals' => [
                'transactions' => $transactions,
                'gross_sales' => $this->money($totals->gross_sales ?? 0),
                'discounts' => $this->money($lineDiscounts + $saleDiscounts + $couponDiscounts),
                'tax' => $this->money($totals->tax ?? 0),
                'shipping' => $this->money($totals->shipping ?? 0),
                'net_sales' => $netSales,
                'amount_paid' => $this->money($totals->paid ?? 0),
                'amount_due' => $this->money($totals->due ?? 0),
                'average_basket' => $transactions > 0 ? $this->money($netSales / $transactions) : 0.0,
            ],
            'payment_methods' => $this->paymentMethods($from, $to, $branchId),
            'taxes' => [
                'total' => $this->money($taxes->sum('amount')),
                'components' => $taxes->values()->all(),
            ],
            'discounts' => [
                'line' => $lineDiscounts,
                'sale' => $saleDiscounts,
                'coupon' => $couponDiscounts,
                'total' => $this->money($lineDiscounts + $saleDiscounts + $couponDiscounts),
            ],
            'invoices' => [
                'count' => $transactions,
                'first' => $this->invoiceNumber($from, $to, $branchId, first: true),
                'last' => $this->invoiceNumber($from, $to, $branchId, first: false),
            ],
            'cash_sessions' => $cash,
            'voids' => $this->voids($from, $to, $branchId),
            'returns' => $this->returns($from, $to, $branchId),
        ];
    }

    /** The same figures rendered onto a thermal-receipt-width Blade template. */
    public function pdf(Business $business, Carbon $date, ?string $branchId = null): PdfDocument
    {
        $report = $this->generate($business, $date, $branchId);

        // 80mm roll = 226.77pt wide. The height is estimated from the row count so a
        // quiet day doesn't spool half a metre of blank paper out of the printer.
        $rows = count($report['payment_methods'])
            + count($report['taxes']['components'])
            + count($report['cash_sessions']['sessions']);

        return Pdf::loadView('pdf.z-report', ['report' => $report])
            ->setPaper([0, 0, 226.77, max(520, 420 + ($rows * 14))]);
    }

    /** Completed sales for the day: one pass, every headline aggregate. */
    private function saleTotals(Carbon $from, Carbon $to, ?string $branchId): object
    {
        return $this->completedSales($from, $to, $branchId)
            ->selectRaw('count(*) as transactions,
                coalesce(sum(subtotal), 0) as gross_sales,
                coalesce(sum(discount_amount), 0) as sale_discounts,
                coalesce(sum(coupon_discount), 0) as coupon_discounts,
                coalesce(sum(tax_amount), 0) as tax,
                coalesce(sum(shipping_amount), 0) as shipping,
                coalesce(sum(total), 0) as net_sales,
                coalesce(sum(paid_amount), 0) as paid,
                coalesce(sum(due_amount), 0) as due')
            // toBase(): these aggregates aren't Sale attributes and shouldn't be hydrated
            // as one. The tenant and soft-delete scopes still apply.
            ->toBase()
            ->first() ?? (object) [];
    }

    /**
     * Tender taken against the day's sales, by method.
     *
     * Grouped by the *sale's* day, not the payment's, so the methods column
     * always reconciles against the same invoices the totals above counted.
     *
     * @return array<int, array<string, mixed>>
     */
    private function paymentMethods(Carbon $from, Carbon $to, ?string $branchId): array
    {
        return SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.status', SaleStatus::Completed)
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($branchId, fn ($q, $id) => $q->where('sales.branch_id', $id))
            ->selectRaw('sale_payments.method, count(*) as transactions, coalesce(sum(sale_payments.amount), 0) as amount')
            ->groupBy('sale_payments.method')
            ->orderByDesc('amount')
            // toBase(): a grouped row is not a SalePayment, and hydrating it as one would
            // run `method` through the enum cast before we can read the raw tender string.
            ->toBase()
            ->get()
            ->map(fn ($row) => [
                'method' => (string) $row->method,
                'label' => PaymentMethod::tryFrom((string) $row->method)?->label() ?? (string) $row->method,
                'count' => (int) $row->transactions,
                'amount' => $this->money($row->amount),
            ])
            ->all();
    }

    /**
     * Tax charged, by component, read from the per-line snapshots rather than
     * the live tax configuration.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function taxComponents(Carbon $from, Carbon $to, ?string $branchId)
    {
        return SaleItemTax::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_item_taxes.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed)
            ->whereNull('sales.deleted_at')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($branchId, fn ($q, $id) => $q->where('sales.branch_id', $id))
            ->selectRaw('sale_item_taxes.name, sale_item_taxes.rate,
                coalesce(sum(sale_item_taxes.base_amount), 0) as base_amount,
                coalesce(sum(sale_item_taxes.amount), 0) as amount')
            ->groupBy('sale_item_taxes.name', 'sale_item_taxes.rate')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'rate' => (float) $row->rate,
                'base_amount' => $this->money($row->base_amount),
                'amount' => $this->money($row->amount),
            ]);
    }

    /** Per-line discounts, which never reach the sale header's own discount column. */
    private function lineDiscounts(Carbon $from, Carbon $to, ?string $branchId): float
    {
        return $this->money(
            SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.status', SaleStatus::Completed)
                ->whereNull('sales.deleted_at')
                ->whereBetween('sales.sold_at', [$from, $to])
                ->when($branchId, fn ($q, $id) => $q->where('sales.branch_id', $id))
                ->sum('sale_items.discount_amount')
        );
    }

    /** First / last invoice number of the day — the range a Z-report is filed under. */
    private function invoiceNumber(Carbon $from, Carbon $to, ?string $branchId, bool $first): ?string
    {
        $direction = $first ? 'asc' : 'desc';

        $value = $this->completedSales($from, $to, $branchId)
            ->orderBy('sold_at', $direction)
            ->orderBy('invoice_number', $direction)
            ->value('invoice_number');

        return $value !== null ? (string) $value : null;
    }

    /**
     * Drawer reconciliation for every session that touched the day, whether it
     * opened or closed inside it. A still-open session has no counted cash yet,
     * so `actual_cash` and `difference` stay null rather than pretending to zero.
     *
     * @return array<string, mixed>
     */
    private function cashSessions(Carbon $from, Carbon $to, ?string $branchId): array
    {
        $sessions = CashRegisterSession::query()
            ->join('cash_registers', 'cash_registers.id', '=', 'cash_register_sessions.cash_register_id')
            ->when($branchId, fn ($q, $id) => $q->where('cash_registers.branch_id', $id))
            ->where(fn ($q) => $q
                ->whereBetween('cash_register_sessions.opened_at', [$from, $to])
                ->orWhereBetween('cash_register_sessions.closed_at', [$from, $to]))
            ->orderBy('cash_register_sessions.opened_at')
            ->with('user:id,name')
            ->select('cash_register_sessions.*', 'cash_registers.name as register_name')
            ->get();

        $counted = $sessions->whereNotNull('actual_cash');

        return [
            'count' => $sessions->count(),
            'opening_cash' => $this->money($sessions->sum('opening_cash')),
            'cash_in' => $this->money($sessions->sum('cash_in')),
            'cash_out' => $this->money($sessions->sum('cash_out')),
            'expected_cash' => $this->money($sessions->sum('expected_cash')),
            'actual_cash' => $counted->isEmpty() ? null : $this->money($counted->sum('actual_cash')),
            'difference' => $counted->isEmpty() ? null : $this->money($counted->sum('difference')),
            'sessions' => $sessions->map(fn (CashRegisterSession $s) => [
                'id' => $s->id,
                'register' => $s->register_name,
                'cashier' => $s->user?->name,
                'status' => $s->status->value,
                'opened_at' => $s->opened_at?->toIso8601String(),
                'closed_at' => $s->closed_at?->toIso8601String(),
                'opening_cash' => $this->money($s->opening_cash),
                'expected_cash' => $this->money($s->expected_cash),
                'actual_cash' => $s->actual_cash === null ? null : $this->money($s->actual_cash),
                'difference' => $s->difference === null ? null : $this->money($s->difference),
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function voids(Carbon $from, Carbon $to, ?string $branchId): array
    {
        $row = Sale::query()
            ->where('status', SaleStatus::Cancelled)
            ->whereBetween('sold_at', [$from, $to])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->selectRaw('count(*) as transactions, coalesce(sum(total), 0) as amount')
            ->toBase()
            ->first();

        return ['count' => (int) ($row->transactions ?? 0), 'amount' => $this->money($row->amount ?? 0)];
    }

    /** @return array<string, mixed> */
    private function returns(Carbon $from, Carbon $to, ?string $branchId): array
    {
        $row = SaleReturn::query()
            ->whereBetween('returned_at', [$from, $to])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id))
            ->selectRaw('count(*) as transactions, coalesce(sum(total), 0) as amount')
            ->toBase()
            ->first();

        return ['count' => (int) ($row->transactions ?? 0), 'amount' => $this->money($row->amount ?? 0)];
    }

    /** @return Builder<Sale> */
    private function completedSales(Carbon $from, Carbon $to, ?string $branchId): Builder
    {
        return Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereBetween('sold_at', [$from, $to])
            ->when($branchId, fn ($q, $id) => $q->where('branch_id', $id));
    }

    private function money(mixed $amount): float
    {
        return round((float) $amount, 2);
    }
}
