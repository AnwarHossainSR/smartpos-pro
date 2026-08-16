<?php

namespace App\Services\Sales;

use App\Domain\Enums\StockMovementType;
use App\Domain\Sales\Data\CreateSaleReturnData;
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

    public function create(Sale $sale, CreateSaleReturnData $data, User $user): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data, $user) {
            $warehouseId = $data->warehouseId ?? $sale->warehouse_id;

            // How much of each line has already been returned.
            $alreadyReturned = DB::table('sale_return_items')
                ->whereIn('sale_item_id', array_map(static fn ($l) => $l->saleItemId, $data->items))
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
                'refund_method' => $data->refundMethod,
                'note' => $data->note,
                'returned_at' => Carbon::now(),
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($data->items as $line) {
                /** @var SaleItem|null $saleItem */
                $saleItem = $sale->items()->with('taxes')->whereKey($line->saleItemId)->first();

                if (! $saleItem) {
                    throw ValidationException::withMessages([
                        'items' => ["Sale item #{$line->saleItemId} does not belong to this sale."],
                    ]);
                }

                $quantity = $line->quantity;
                $returnable = (float) $saleItem->quantity - (float) ($alreadyReturned[$saleItem->id] ?? 0);

                if ($quantity <= 0 || $quantity > $returnable) {
                    throw ValidationException::withMessages([
                        'items' => ["Cannot return {$quantity} of '{$saleItem->name}'; only {$returnable} is returnable."],
                    ]);
                }

                // What fraction of the original line is being reversed. Applied to
                // each snapshotted component rather than recomputed at today's
                // rate, so a rate change between sale and return cannot change
                // the refund.
                $ratio = (float) $saleItem->quantity > 0 ? $quantity / (float) $saleItem->quantity : 0.0;

                $lineTotal = round((float) $saleItem->unit_price * $quantity, 2);
                $subtotal += $lineTotal;

                $returnItem = $return->items()->create([
                    'sale_item_id' => $saleItem->id,
                    'product_id' => $saleItem->product_id,
                    'variant_id' => $saleItem->variant_id,
                    'quantity' => $quantity,
                    'unit_price' => (float) $saleItem->unit_price,
                    'unit_cost' => (float) $saleItem->unit_cost,
                    'line_total' => $lineTotal,
                ]);

                foreach ($saleItem->taxes as $component) {
                    $amount = round((float) $component->amount * $ratio, 2);
                    $taxTotal += $amount;

                    $returnItem->taxes()->create([
                        'tax_rate_id' => $component->tax_rate_id,
                        'name' => $component->name,
                        'rate' => $component->rate,
                        'type' => $component->type,
                        'is_compound' => $component->is_compound,
                        'base_amount' => round((float) $component->base_amount * $ratio, 2),
                        'amount' => $amount,
                    ]);
                }

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
            // Tail, not head: UUIDv7's leading bytes are a timestamp, so rows
            // created in the same millisecond would collide on a head slice.
            $return->update([
                'reference_number' => 'RET-'.strtoupper(substr($return->id, -8)),
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

            return $return->load('items.taxes');
        });
    }
}
