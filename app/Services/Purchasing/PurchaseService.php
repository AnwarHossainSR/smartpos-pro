<?php

namespace App\Services\Purchasing;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Handles purchase creation and goods receipt. When a purchase is received, each
 * line increments stock through the immutable ledger (mirroring how a sale
 * decrements it), the supplier's payable balance is updated, and product cost is
 * refreshed — all inside a single transaction.
 */
class PurchaseService
{
    public function __construct(protected StockLedgerService $ledger) {}

    /**
     * @param  array{
     *   warehouse_id:int, supplier_id?:int|null, status?:string, note?:string|null,
     *   discount_amount?:float, shipping_amount?:float,
     *   items: array<int, array{product_id:int, quantity:float, unit_cost:float, variant_id?:int|null, discount_amount?:float, tax_amount?:float}>,
     *   payments?: array<int, array{method:string, amount:float, reference?:string|null}>
     * }  $payload
     */
    public function create(array $payload, User $user): Purchase
    {
        return DB::transaction(function () use ($payload, $user) {
            $status = $payload['status'] ?? 'received';
            $receivesStock = $status === 'received';

            $lines = $this->buildLines($payload['items']);

            $subtotal = array_sum(array_column($lines, 'line_total'));
            $taxTotal = array_sum(array_column($lines, 'tax_amount'));
            $discount = round((float) ($payload['discount_amount'] ?? 0), 2);
            $shipping = round((float) ($payload['shipping_amount'] ?? 0), 2);
            $total = round($subtotal + $taxTotal + $shipping - $discount, 2);

            $payments = $payload['payments'] ?? [];
            $paid = round((float) array_sum(array_column($payments, 'amount')), 2);
            $due = max(0, round($total - $paid, 2));

            $purchase = Purchase::create([
                'business_id' => $user->business_id,
                'warehouse_id' => $payload['warehouse_id'],
                'supplier_id' => $payload['supplier_id'] ?? null,
                'user_id' => $user->id,
                'reference_number' => 'PENDING-'.uniqid(),
                'status' => $status,
                'payment_status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due'),
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $discount,
                'tax_amount' => round($taxTotal, 2),
                'shipping_amount' => $shipping,
                'total' => $total,
                'paid_amount' => min($paid, $total),
                'due_amount' => $due,
                'note' => $payload['note'] ?? null,
                'purchased_at' => Carbon::now(),
            ]);

            $purchase->update(['reference_number' => 'PUR-'.str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($lines as $line) {
                $purchase->items()->create($line['attributes']);

                if ($receivesStock) {
                    $this->ledger->record([
                        'product_id' => $line['attributes']['product_id'],
                        'variant_id' => $line['attributes']['variant_id'],
                        'warehouse_id' => $payload['warehouse_id'],
                        'direction' => 'in',
                        'quantity' => $line['attributes']['quantity'],
                        'unit_cost' => $line['attributes']['unit_cost'],
                        'type' => 'purchase',
                        'reference' => $purchase,
                        'user_id' => $user->id,
                    ]);

                    // Keep the product's default cost in step with the latest purchase.
                    Product::whereKey($line['attributes']['product_id'])
                        ->update(['purchase_price' => $line['attributes']['unit_cost']]);
                }
            }

            if ($due > 0 && $purchase->supplier_id) {
                Supplier::whereKey($purchase->supplier_id)->increment('balance', $due);
            }

            return $purchase->load('items', 'supplier');
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{attributes: array<string, mixed>, line_total: float, tax_amount: float}>
     */
    protected function buildLines(array $items): array
    {
        $productIds = collect($items)->pluck('product_id')->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lines = [];

        foreach ($items as $item) {
            if (! $products->has($item['product_id'])) {
                throw new \InvalidArgumentException("Product #{$item['product_id']} does not exist in this tenant.");
            }

            $quantity = round((float) $item['quantity'], 4);
            $unitCost = round((float) $item['unit_cost'], 4);
            $discount = round((float) ($item['discount_amount'] ?? 0), 2);
            $tax = round((float) ($item['tax_amount'] ?? 0), 2);
            $lineTotal = max(0, ($unitCost * $quantity) - $discount);

            $lines[] = [
                'attributes' => [
                    'product_id' => (int) $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'line_total' => round($lineTotal + $tax, 2),
                ],
                'line_total' => round($lineTotal, 2),
                'tax_amount' => $tax,
            ];
        }

        return $lines;
    }
}
