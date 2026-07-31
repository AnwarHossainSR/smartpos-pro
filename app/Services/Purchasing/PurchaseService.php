<?php

namespace App\Services\Purchasing;

use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\StockMovementType;
use App\Domain\Purchasing\Data\CreatePurchaseData;
use App\Domain\Purchasing\Data\PurchaseLineData;
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

    public function create(CreatePurchaseData $data, User $user): Purchase
    {
        return DB::transaction(function () use ($data, $user) {
            $receivesStock = $data->status->affectsStock();

            $this->assertProductsExist($data->items);

            $subtotal = round(array_sum(array_map($this->netOf(...), $data->items)), 2);
            $taxTotal = round(array_sum(array_map(static fn (PurchaseLineData $l) => $l->taxAmount, $data->items)), 2);
            $total = round($subtotal + $taxTotal + $data->shippingAmount - $data->discountAmount, 2);

            $paid = $data->paidAmount();
            $due = max(0, round($total - $paid, 2));

            $purchase = Purchase::create([
                'business_id' => $user->business_id,
                'warehouse_id' => $data->warehouseId,
                'supplier_id' => $data->supplierId,
                'user_id' => $user->id,
                'reference_number' => 'PENDING-'.uniqid(),
                'status' => $data->status,
                'payment_status' => PaymentStatus::fromAmounts($paid, $total),
                'subtotal' => $subtotal,
                'discount_amount' => $data->discountAmount,
                'tax_amount' => $taxTotal,
                'shipping_amount' => $data->shippingAmount,
                'total' => $total,
                'paid_amount' => min($paid, $total),
                'due_amount' => $due,
                'note' => $data->note,
                'purchased_at' => Carbon::now(),
            ]);

            $purchase->update(['reference_number' => 'PUR-'.str_pad((string) $purchase->id, 6, '0', STR_PAD_LEFT)]);

            foreach ($data->items as $line) {
                $purchase->items()->create([
                    'product_id' => $line->productId,
                    'variant_id' => $line->variantId,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unitCost,
                    'discount_amount' => $line->discountAmount,
                    'tax_amount' => $line->taxAmount,
                    'line_total' => round($this->netOf($line) + $line->taxAmount, 2),
                ]);

                if ($receivesStock) {
                    $this->ledger->record([
                        'product_id' => $line->productId,
                        'variant_id' => $line->variantId,
                        'warehouse_id' => $data->warehouseId,
                        'quantity' => $line->quantity,
                        'unit_cost' => $line->unitCost,
                        'type' => StockMovementType::Purchase,
                        'reference' => $purchase,
                        'user_id' => $user->id,
                    ]);

                    // Keep the product's default cost in step with the latest purchase.
                    Product::whereKey($line->productId)->update(['purchase_price' => $line->unitCost]);
                }
            }

            if ($due > 0 && $purchase->supplier_id) {
                Supplier::whereKey($purchase->supplier_id)->increment('balance', $due);
            }

            return $purchase->load('items', 'supplier');
        });
    }

    /** Line cost net of its discount and excluding tax. */
    private function netOf(PurchaseLineData $line): float
    {
        return round(max(0, ($line->unitCost * $line->quantity) - $line->discountAmount), 2);
    }

    /**
     * The form request already scopes `exists:` by tenant; this guards the
     * service against internal callers that bypass it.
     *
     * @param  array<int, PurchaseLineData>  $items
     */
    private function assertProductsExist(array $items): void
    {
        $ids = array_map(static fn (PurchaseLineData $l) => $l->productId, $items);
        $found = Product::whereIn('id', $ids)->pluck('id')->all();

        foreach (array_diff($ids, $found) as $missing) {
            throw new \InvalidArgumentException("Product #{$missing} does not exist in this tenant.");
        }
    }
}
