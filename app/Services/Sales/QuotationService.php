<?php

namespace App\Services\Sales;

use App\Domain\Enums\QuotationStatus;
use App\Domain\Sales\Data\CreateSaleData;
use App\Domain\Tax\TaxableLine;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxEngine;
use App\Domain\Tax\TaxResolver;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuotationService
{
    public function __construct(
        protected SaleService $saleService,
        protected TaxEngine $tax,
        protected TaxResolver $taxes,
    ) {}

    /**
     * @param  array{
     *   branch_id:int, customer_id?:int|null, note?:string|null, terms?:string|null,
     *   valid_until?:string|null, status?:string,
     *   items: array<int, array{product_id:int, quantity:float, unit_price?:float, discount?:float}>
     * }  $payload
     */
    public function create(array $payload, User $user): Quotation
    {
        return DB::transaction(function () use ($payload, $user) {
            $context = $this->taxes->contextFor(
                $user->business ?? Business::findOrFail($user->business_id),
                Branch::find($payload['branch_id']),
                isset($payload['customer_id']) ? Customer::find($payload['customer_id']) : null,
            );

            ['subtotal' => $subtotal, 'taxAmount' => $taxAmount, 'total' => $total, 'lines' => $lines]
                = $this->calculateTotals($payload['items'], $context);

            $quotation = Quotation::create([
                'tenant_id' => $user->tenant_id,
                'branch_id' => $payload['branch_id'],
                'customer_id' => $payload['customer_id'] ?? null,
                'user_id' => $user->id,
                'reference_number' => 'QUO-'.strtoupper(uniqid()),
                'status' => QuotationStatus::from($payload['status'] ?? QuotationStatus::Draft->value),
                'subtotal' => $subtotal,
                'discount_amount' => 0,
                'tax_amount' => $taxAmount,
                'total' => $total,
                'note' => $payload['note'] ?? null,
                'terms' => $payload['terms'] ?? null,
                'valid_until' => $payload['valid_until'] ?? null,
            ]);

            foreach ($lines as $line) {
                QuotationItem::create(array_merge($line, ['quotation_id' => $quotation->id]));
            }

            return $quotation->load(['items.product', 'customer']);
        });
    }

    /**
     * Convert a quotation to a confirmed sale.
     *
     * @param  array{warehouse_id:int, payments?:array}  $payload
     */
    public function convertToSale(Quotation $quotation, array $payload, User $user): Sale
    {
        if (! in_array($quotation->status, [QuotationStatus::Draft, QuotationStatus::Sent, QuotationStatus::Accepted], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft/sent/accepted quotations can be converted.']);
        }
        if ($quotation->isExpired()) {
            throw ValidationException::withMessages(['valid_until' => 'This quotation has expired.']);
        }

        return DB::transaction(function () use ($quotation, $payload, $user) {
            $items = $quotation->items->map(fn ($qi) => [
                'product_id' => $qi->product_id,
                'variant_id' => $qi->variant_id,
                'quantity' => $qi->quantity,
                'unit_price' => $qi->unit_price,
            ])->all();

            $sale = $this->saleService->create(CreateSaleData::fromArray([
                'branch_id' => $quotation->branch_id,
                'warehouse_id' => $payload['warehouse_id'],
                'customer_id' => $quotation->customer_id,
                'items' => $items,
                'payments' => $payload['payments'] ?? [],
                'note' => "Converted from quotation {$quotation->reference_number}",
            ]), $user);

            $quotation->update([
                'status' => QuotationStatus::Converted,
                'converted_to_sale_id' => $sale->id,
            ]);

            return $sale;
        });
    }

    private function calculateTotals(array $items, TaxContext $context): array
    {
        $products = Product::whereIn('id', array_column($items, 'product_id'))
            ->with(TaxEngine::PRODUCT_RELATIONS)
            ->get()
            ->keyBy('id');

        $resolved = [];
        $taxable = [];

        foreach ($items as $index => $item) {
            $product = $products->get($item['product_id']) ?? Product::findOrFail($item['product_id']);
            $unitPrice = $item['unit_price'] ?? (float) $product->selling_price;
            $qty = (float) $item['quantity'];
            $discount = (float) ($item['discount'] ?? 0);
            $amount = max(0, ($unitPrice - $discount) * $qty);

            $resolved[$index] = compact('item', 'qty', 'unitPrice', 'discount');
            $taxable[$index] = new TaxableLine($amount, $qty, $product);
        }

        $breakdown = $this->tax->taxDocument($taxable, $context);

        $subtotal = 0.0;
        $taxAmount = 0.0;
        $lines = [];

        foreach ($resolved as $index => $r) {
            $lineTax = $breakdown->lineFor($index);
            $lineSubtotal = $lineTax->net->toFloat();

            $subtotal += $lineSubtotal;
            $taxAmount += $lineTax->tax->toFloat();

            $lines[] = [
                'product_id' => $r['item']['product_id'],
                'variant_id' => $r['item']['variant_id'] ?? null,
                'quantity' => $r['qty'],
                'unit_price' => $r['unitPrice'],
                'discount' => $r['discount'],
                'tax_rate' => $lineTax->effectiveRate(),
                'subtotal' => $lineSubtotal,
            ];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'taxAmount' => round($taxAmount, 2),
            'total' => round($subtotal + $taxAmount, 2),
            'lines' => $lines,
        ];
    }
}
