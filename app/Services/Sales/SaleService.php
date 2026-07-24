<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates POS checkout. The entire sale — header, line items, stock
 * decrements and payments — is created inside one database transaction so a
 * failure anywhere (e.g. insufficient stock on the last line) rolls the whole
 * sale back and never leaves partial inventory or orphan records behind.
 */
class SaleService
{
    public function __construct(protected StockLedgerService $ledger) {}

    /**
     * @param  array{
     *   branch_id:int, warehouse_id:int, customer_id?:int|null, status?:string,
     *   note?:string|null, discount_amount?:float, shipping_amount?:float,
     *   items: array<int, array{product_id:int, quantity:float, unit_price?:float, variant_id?:int|null, discount_amount?:float}>,
     *   payments?: array<int, array{method:string, amount:float, reference?:string|null}>
     * }  $payload
     */
    public function create(array $payload, User $cashier): Sale
    {
        return DB::transaction(function () use ($payload, $cashier) {
            $status = $payload['status'] ?? 'completed';
            $affectsStock = $status === 'completed';

            $lines = $this->buildLines($payload['items']);

            $subtotal = array_sum(array_column($lines, 'line_total'));
            $taxTotal = array_sum(array_column($lines, 'tax_amount'));
            $discount = round((float) ($payload['discount_amount'] ?? 0), 2);
            $shipping = round((float) ($payload['shipping_amount'] ?? 0), 2);
            $total = round($subtotal + $taxTotal + $shipping - $discount, 2);

            $payments = $payload['payments'] ?? [];
            $paid = round((float) array_sum(array_column($payments, 'amount')), 2);
            $change = max(0, round($paid - $total, 2));
            $due = max(0, round($total - $paid, 2));

            $sale = Sale::create([
                'business_id' => $cashier->business_id,
                'branch_id' => $payload['branch_id'],
                'warehouse_id' => $payload['warehouse_id'],
                'customer_id' => $payload['customer_id'] ?? null,
                'user_id' => $cashier->id,
                'invoice_number' => 'PENDING-'.uniqid(),
                'status' => $status,
                'payment_status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due'),
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $discount,
                'tax_amount' => round($taxTotal, 2),
                'shipping_amount' => $shipping,
                'total' => $total,
                'paid_amount' => min($paid, $total),
                'change_amount' => $change,
                'due_amount' => $due,
                'note' => $payload['note'] ?? null,
                'sold_at' => Carbon::now(),
            ]);

            // Deterministic, human-readable invoice number derived from the row id.
            $sale->update(['invoice_number' => 'INV-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($lines as $line) {
                $sale->items()->create($line['attributes']);

                if ($affectsStock && $line['track_inventory']) {
                    $this->ledger->record([
                        'product_id' => $line['attributes']['product_id'],
                        'variant_id' => $line['attributes']['variant_id'],
                        'warehouse_id' => $payload['warehouse_id'],
                        'direction' => 'out',
                        'quantity' => $line['attributes']['quantity'],
                        'unit_cost' => $line['attributes']['unit_cost'],
                        'type' => 'sale',
                        'reference' => $sale,
                        'user_id' => $cashier->id,
                    ]);
                }
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'method' => $payment['method'],
                    'amount' => round((float) $payment['amount'], 2),
                    'reference' => $payment['reference'] ?? null,
                    'paid_at' => Carbon::now(),
                ]);
            }

            if ($due > 0 && $sale->customer_id) {
                Customer::whereKey($sale->customer_id)->increment('balance', $due);
            }

            return $sale->load('items', 'payments', 'customer');
        });
    }

    /**
     * Resolve each requested line against the live product record, snapshotting
     * price/cost/tax so later product edits never mutate historical invoices.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{attributes: array<string, mixed>, track_inventory: bool, line_total: float, tax_amount: float}>
     */
    protected function buildLines(array $items): array
    {
        $productIds = collect($items)->pluck('product_id')->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lines = [];

        foreach ($items as $item) {
            /** @var Product $product */
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw new \InvalidArgumentException("Product #{$item['product_id']} does not exist in this tenant.");
            }

            $quantity = round((float) $item['quantity'], 4);
            $unitPrice = round((float) ($item['unit_price'] ?? $product->selling_price), 2);
            $lineDiscount = round((float) ($item['discount_amount'] ?? 0), 2);
            $taxable = max(0, ($unitPrice * $quantity) - $lineDiscount);
            $taxAmount = $product->tax_inclusive
                ? round($taxable - ($taxable / (1 + ((float) $product->tax_rate / 100))), 2)
                : round($taxable * ((float) $product->tax_rate / 100), 2);
            $lineTotal = $product->tax_inclusive ? $taxable : $taxable + $taxAmount;

            $lines[] = [
                'attributes' => [
                    'product_id' => $product->id,
                    'variant_id' => $item['variant_id'] ?? null,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'unit_cost' => (float) $product->purchase_price,
                    'discount_amount' => $lineDiscount,
                    'tax_rate' => (float) $product->tax_rate,
                    'tax_amount' => $taxAmount,
                    'line_total' => round($lineTotal, 2),
                ],
                'track_inventory' => (bool) $product->track_inventory,
                'line_total' => round($product->tax_inclusive ? $taxable - $taxAmount : $taxable, 2),
                'tax_amount' => $taxAmount,
            ];
        }

        return $lines;
    }
}
