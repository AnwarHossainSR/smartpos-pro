<?php

namespace App\Services\Sales;

use App\Domain\Enums\RefundMethod;
use App\Domain\Enums\StockMovementType;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Processes a return/refund against a completed sale. Returned quantities flow
 * back into stock through the ledger, the refund reduces any outstanding
 * customer due first (remainder is a cash/card refund), and over-returning
 * beyond what was sold (net of prior returns) is rejected.
 */
class SaleReturnService
{
    public function __construct(protected StockLedgerService $ledger) {}

    /**
     * @param  array{warehouse_id?:int|null, refund_method?:string, note?:string|null,
     *   items: array<int, array{sale_item_id:int, quantity:float}>}  $payload
     */
    public function create(Sale $sale, array $payload, User $user): SaleReturn
    {
        return DB::transaction(function () use ($sale, $payload, $user) {
            $warehouseId = $payload['warehouse_id'] ?? $sale->warehouse_id;

            // How much of each line has already been returned.
            $alreadyReturned = DB::table('sale_return_items')
                ->whereIn('sale_item_id', collect($payload['items'])->pluck('sale_item_id'))
                ->groupBy('sale_item_id')
                ->selectRaw('sale_item_id, sum(quantity) as qty')
                ->pluck('qty', 'sale_item_id');

            $return = SaleReturn::create([
                'sale_id' => $sale->id,
                'branch_id' => $sale->branch_id,
                'warehouse_id' => $warehouseId,
                'customer_id' => $sale->customer_id,
                'user_id' => $user->id,
                'reference_number' => 'PENDING-'.uniqid(),
                'refund_method' => RefundMethod::from($payload['refund_method'] ?? RefundMethod::Cash->value),
                'note' => $payload['note'] ?? null,
                'returned_at' => Carbon::now(),
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($payload['items'] as $line) {
                /** @var SaleItem|null $saleItem */
                $saleItem = $sale->items()->whereKey($line['sale_item_id'])->first();

                if (! $saleItem) {
                    throw ValidationException::withMessages([
                        'items' => ["Sale item #{$line['sale_item_id']} does not belong to this sale."],
                    ]);
                }

                $quantity = round((float) $line['quantity'], 4);
                $returnable = (float) $saleItem->quantity - (float) ($alreadyReturned[$saleItem->id] ?? 0);

                if ($quantity <= 0 || $quantity > $returnable) {
                    throw ValidationException::withMessages([
                        'items' => ["Cannot return {$quantity} of '{$saleItem->name}'; only {$returnable} is returnable."],
                    ]);
                }

                $lineTotal = round((float) $saleItem->unit_price * $quantity, 2);
                $lineTax = $saleItem->quantity > 0
                    ? round((float) $saleItem->tax_amount * ($quantity / (float) $saleItem->quantity), 2)
                    : 0;
                $subtotal += $lineTotal;
                $taxTotal += $lineTax;

                $return->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'variant_id' => $saleItem->variant_id,
                    'quantity' => $quantity,
                    'unit_price' => (float) $saleItem->unit_price,
                    'unit_cost' => (float) $saleItem->unit_cost,
                    'line_total' => $lineTotal,
                ]);

                // Return the goods to stock via the immutable ledger.
                $this->ledger->record([
                    'product_id' => $saleItem->product_id,
                    'variant_id' => $saleItem->variant_id,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'unit_cost' => (float) $saleItem->unit_cost,
                    'type' => StockMovementType::SaleReturn,
                    'reference' => $return,
                    'user_id' => $user->id,
                ]);
            }

            $total = round($subtotal + $taxTotal, 2);
            $return->update([
                'reference_number' => 'RET-'.str_pad((string) $return->id, 6, '0', STR_PAD_LEFT),
                'subtotal' => round($subtotal, 2),
                'tax_amount' => round($taxTotal, 2),
                'total' => $total,
            ]);

            // Refund reduces the customer's outstanding due first.
            if ($sale->customer_id) {
                $customer = Customer::whereKey($sale->customer_id)->first();
                if ($customer && (float) $customer->balance > 0) {
                    $customer->decrement('balance', min($total, (float) $customer->balance));
                }
            }

            return $return->load('items');
        });
    }
}
