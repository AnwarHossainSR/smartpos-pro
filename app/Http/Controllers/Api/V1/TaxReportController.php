<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\PurchaseStatus;
use App\Domain\Enums\SaleStatus;
use App\Models\Business;
use App\Models\PurchaseItemTax;
use App\Models\SaleItemTax;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Output tax (what was charged on sales) and input tax (what was paid on
 * purchases), by component, over a period — the two figures a VAT/GST return
 * reconciles against. Read from the snapshotted `*_item_taxes` breakdown, not
 * recomputed, so the report matches exactly what invoices said at the time.
 */
class TaxReportController extends ReportController
{
    use ApiResponse;

    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $output = $this->outputTaxByComponent($from, $to, $request->integer('branch_id') ?: null);
        $input = $this->inputTaxByComponent($from, $to);

        $outputTotal = round((float) $output->sum('amount'), 2);
        $inputTotal = round((float) $input->sum('amount'), 2);

        return $this->success([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'output_tax' => ['total' => $outputTotal, 'components' => $output->values()],
            'input_tax' => ['total' => $inputTotal, 'components' => $input->values()],
            'net_payable' => round($outputTotal - $inputTotal, 2),
        ]);
    }

    /** The same figures, shaped for filing: the profile's own label and required fields. */
    public function taxReturn(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $business = Business::find($request->user()->business_id);
        $profile = $business?->effectiveTaxProfile();

        $output = $this->outputTaxByComponent($from, $to, $request->integer('branch_id') ?: null);
        $input = $this->inputTaxByComponent($from, $to);

        $outputTotal = round((float) $output->sum('amount'), 2);
        $inputTotal = round((float) $input->sum('amount'), 2);

        return $this->success([
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'business' => [
                'name' => $business?->name,
                'tax_number_label' => $profile?->tax_number_label ?? 'Tax number',
                'tax_number' => $business?->tax_number,
            ],
            'profile' => [
                'code' => $profile?->code,
                'name' => $profile?->name,
                'required_invoice_fields' => $profile?->required_invoice_fields ?? [],
            ],
            'output_tax' => ['total' => $outputTotal, 'components' => $output->values()],
            'input_tax' => ['total' => $inputTotal, 'components' => $input->values()],
            'net_payable' => round($outputTotal - $inputTotal, 2),
        ]);
    }

    private function outputTaxByComponent(Carbon $from, Carbon $to, ?string $branchId)
    {
        return SaleItemTax::query()
            ->join('sale_items', 'sale_items.id', '=', 'sale_item_taxes.sale_item_id')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($branchId, fn ($q, $id) => $q->where('sales.branch_id', $id))
            ->selectRaw('sale_item_taxes.name, sale_item_taxes.rate,
                sum(sale_item_taxes.base_amount) as base_amount, sum(sale_item_taxes.amount) as amount')
            ->groupBy('sale_item_taxes.name', 'sale_item_taxes.rate')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'rate' => (float) $r->rate,
                'base_amount' => round((float) $r->base_amount, 2),
                'amount' => round((float) $r->amount, 2),
            ]);
    }

    private function inputTaxByComponent(Carbon $from, Carbon $to)
    {
        return PurchaseItemTax::query()
            ->join('purchase_items', 'purchase_items.id', '=', 'purchase_item_taxes.purchase_item_id')
            ->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->where('purchases.status', PurchaseStatus::Received)
            ->whereBetween('purchases.purchased_at', [$from, $to])
            ->selectRaw('purchase_item_taxes.name, purchase_item_taxes.rate,
                sum(purchase_item_taxes.base_amount) as base_amount, sum(purchase_item_taxes.amount) as amount')
            ->groupBy('purchase_item_taxes.name', 'purchase_item_taxes.rate')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($r) => [
                'name' => $r->name,
                'rate' => (float) $r->rate,
                'base_amount' => round((float) $r->base_amount, 2),
                'amount' => round((float) $r->amount, 2),
            ]);
    }
}
