<?php

namespace App\Services\Purchasing;

use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\PurchaseStatus;
use App\Domain\Enums\StockMovementType;
use App\Domain\Purchasing\Data\CreatePurchaseData;
use App\Domain\Purchasing\Data\PurchaseLineData;
use App\Domain\Tax\TaxableLine;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxEngine;
use App\Domain\Tax\TaxResolver;
use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Accounting\TradingJournalService;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(
        protected StockLedgerService $ledger,
        protected TaxEngine $tax,
        protected TaxResolver $taxes,
        protected TradingJournalService $journals,
    ) {}

    public function create(CreatePurchaseData $data, User $user): Purchase
    {
        return DB::transaction(function () use ($data, $user) {
            $this->assertProductsExist($data->items);

            $context = $this->taxContextFor($data, $user);
            $products = Product::whereIn('id', array_map(static fn (PurchaseLineData $l) => $l->productId, $data->items))
                ->with(TaxEngine::PRODUCT_RELATIONS)
                ->get()
                ->keyBy('id');

            $taxable = [];
            foreach ($data->items as $index => $line) {
                $taxable[$index] = new TaxableLine($this->netOf($line), $line->quantity, $products->get($line->productId));
            }
            $breakdown = $this->tax->taxDocument($taxable, $context);

            $subtotal = round(array_sum(array_map($this->netOf(...), $data->items)), 2);
            $taxTotal = $breakdown->taxTotal->toFloat();
            $total = round($subtotal + $taxTotal + $data->shippingAmount - $data->discountAmount, 2);

            $paid = $data->paidAmount();
            $due = max(0, round($total - $paid, 2));

            $purchase = Purchase::create([
                'business_id' => $user->business_id,
                'warehouse_id' => $data->warehouseId,
                'supplier_id' => $data->supplierId,
                'user_id' => $user->id,
                'reference_number' => 'PENDING-'.uniqid(),
                'status' => PurchaseStatus::Draft,
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

            $purchase->update(['reference_number' => 'PUR-'.strtoupper(substr($purchase->id, -8))]);

            foreach ($data->items as $index => $line) {
                $lineTax = $breakdown->lineFor($index);

                $purchase->items()->create([
                    'product_id' => $line->productId,
                    'variant_id' => $line->variantId,
                    'quantity' => $line->quantity,
                    'accepted_qty' => 0,
                    'unit_cost' => $line->unitCost,
                    'discount_amount' => $line->discountAmount,
                    'tax_amount' => $lineTax->tax->toFloat(),
                    'line_total' => $lineTax->gross()->toFloat(),
                ]);
            }

            return $purchase->load('items', 'supplier');
        });
    }

    public function approve(Purchase $purchase, User $user): Purchase
    {
        if ($purchase->status !== PurchaseStatus::Draft) {
            throw new \DomainException('Only draft purchases can be approved.');
        }

        $purchase->update([
            'status' => PurchaseStatus::Approved,
            'approved_at' => Carbon::now(),
        ]);

        return $purchase->fresh('items', 'supplier');
    }

    public function receive(Purchase $purchase, array $items, User $user): Purchase
    {
        if (! in_array($purchase->status, [PurchaseStatus::Approved, PurchaseStatus::Partial], true)) {
            throw new \DomainException('Only approved or partially received purchases can be received.');
        }

        return DB::transaction(function () use ($purchase, $items, $user) {
            $totalReceived = 0;

            foreach ($items as $itemData) {
                $purchaseItem = PurchaseItem::where('purchase_id', $purchase->id)
                    ->where('id', $itemData['id'])
                    ->first();

                if (! $purchaseItem) {
                    throw new \DomainException("Purchase item #{$itemData['id']} not found.");
                }

                $acceptedQty = (float) $itemData['accepted_qty'];
                $previousAccepted = (float) $purchaseItem->accepted_qty;
                $delta = $acceptedQty - $previousAccepted;

                if ($delta < 0) {
                    throw new \DomainException('Accepted quantity cannot be reduced.');
                }

                if ($acceptedQty > (float) $purchaseItem->quantity) {
                    throw new \DomainException('Accepted quantity cannot exceed ordered quantity.');
                }

                if ($delta > 0) {
                    $this->ledger->record([
                        'product_id' => $purchaseItem->product_id,
                        'variant_id' => $purchaseItem->variant_id,
                        'warehouse_id' => $purchase->warehouse_id,
                        'quantity' => $delta,
                        'unit_cost' => (float) $purchaseItem->unit_cost,
                        'type' => StockMovementType::Purchase,
                        'reference' => $purchase,
                        'user_id' => $user->id,
                    ]);

                    Product::whereKey($purchaseItem->product_id)->update([
                        'purchase_price' => $purchaseItem->unit_cost,
                    ]);
                }

                $purchaseItem->update(['accepted_qty' => $acceptedQty]);
                $totalReceived += $acceptedQty;
            }

            $allItems = $purchase->items()->get();
            $allFullyReceived = $allItems->every(
                fn (PurchaseItem $item) => (float) $item->accepted_qty >= (float) $item->quantity
            );

            $newStatus = $allFullyReceived ? PurchaseStatus::Received : PurchaseStatus::Partial;

            $purchase->update([
                'status' => $newStatus,
                'received_at' => $allFullyReceived ? Carbon::now() : null,
            ]);

            if ((float) $purchase->due_amount > 0 && $purchase->supplier_id) {
                Supplier::whereKey($purchase->supplier_id)->increment('balance', (float) $purchase->due_amount);
            }

            $this->journals->postPurchase($purchase);

            return $purchase->fresh('items', 'supplier');
        });
    }

    public function cancel(Purchase $purchase): Purchase
    {
        if (! in_array($purchase->status, [PurchaseStatus::Draft, PurchaseStatus::Approved], true)) {
            throw new \DomainException('Only draft or approved purchases can be cancelled.');
        }

        $hasReceivedItems = $purchase->items()
            ->where('accepted_qty', '>', 0)
            ->exists();

        if ($hasReceivedItems) {
            throw new \DomainException('Cannot cancel a purchase with received items.');
        }

        $purchase->update(['status' => PurchaseStatus::Cancelled]);

        return $purchase->fresh('items', 'supplier');
    }

    private function netOf(PurchaseLineData $line): float
    {
        return round(max(0, ($line->unitCost * $line->quantity) - $line->discountAmount), 2);
    }

    private function taxContextFor(CreatePurchaseData $data, User $user): TaxContext
    {
        $branch = Warehouse::find($data->warehouseId)?->branch;

        return $this->taxes
            ->contextFor($user->business ?? Business::findOrFail($user->business_id), $branch)
            ->asExclusive();
    }

    private function assertProductsExist(array $items): void
    {
        $ids = array_map(static fn (PurchaseLineData $l) => $l->productId, $items);
        $found = Product::whereIn('id', $ids)->pluck('id')->all();

        foreach (array_diff($ids, $found) as $missing) {
            throw new \InvalidArgumentException("Product #{$missing} does not exist in this tenant.");
        }
    }
}
