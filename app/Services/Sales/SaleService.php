<?php

namespace App\Services\Sales;

use App\Domain\Enums\PaymentStatus;
use App\Domain\Enums\SaleStatus;
use App\Domain\Enums\StockMovementType;
use App\Domain\Sales\Data\CollectPaymentData;
use App\Domain\Sales\Data\CreateSaleData;
use App\Domain\Sales\Data\PaymentData;
use App\Domain\Sales\Pricing\CouponRedeemer;
use App\Domain\Sales\Pricing\PricedLine;
use App\Domain\Sales\Pricing\SaleLineBuilder;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxResolver;
use App\Models\Branch;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Inventory\StockLedgerService;
use App\Services\Loyalty\LoyaltyService;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Orchestrates POS checkout.
 *
 * Pricing lives in {@see SaleLineBuilder} and coupon redemption in
 * {@see CouponRedeemer}; what remains here is the sequence and the transaction
 * boundary. The entire sale — header, lines, stock decrements and payments — is
 * created inside one transaction, so a failure anywhere (insufficient stock on
 * the last line, an exhausted coupon) rolls the whole thing back and never
 * leaves partial inventory or orphan records behind.
 */
class SaleService
{
    public function __construct(
        protected StockLedgerService $ledger,
        protected AuditLogService $audit,
        protected SaleLineBuilder $lineBuilder,
        protected CouponRedeemer $coupons,
        protected TaxResolver $taxes,
    ) {}

    public function create(CreateSaleData $data, User $cashier): Sale
    {
        return DB::transaction(function () use ($data, $cashier) {
            $affectsStock = $data->status->affectsStock();

            $basket = $this->lineBuilder->build($data->items, $cashier, $this->taxContextFor($data, $cashier));
            $subtotal = $basket->subtotal();

            $discount = $this->resolveSaleDiscount($data, $subtotal, $cashier);
            $redemption = $data->couponCode !== null
                ? $this->coupons->redeem($data->couponCode, $subtotal)
                : null;

            $total = max(0, round(
                $subtotal + $basket->taxTotal() + $data->shippingAmount - $discount - ($redemption?->discount ?? 0),
                2,
            ));

            $paid = $data->paidAmount();
            $change = max(0, round($paid - $total, 2));
            $due = max(0, round($total - $paid, 2));

            $sale = Sale::create([
                'business_id' => $cashier->business_id,
                'branch_id' => $data->branchId,
                'warehouse_id' => $data->warehouseId,
                'customer_id' => $data->customerId,
                'user_id' => $cashier->id,
                'coupon_id' => $redemption?->coupon->id,
                'invoice_number' => 'PENDING-'.uniqid(),
                'status' => $data->status,
                'payment_status' => PaymentStatus::fromAmounts($paid, $total),
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'coupon_discount' => round($redemption?->discount ?? 0, 2),
                'tax_amount' => $basket->taxTotal(),
                'shipping_amount' => $data->shippingAmount,
                'total' => $total,
                'paid_amount' => min($paid, $total),
                'change_amount' => $change,
                'due_amount' => $due,
                'note' => $data->note,
                'sold_at' => Carbon::now(),
            ]);

            // Deterministic, human-readable invoice number derived from the row id.
            $sale->update(['invoice_number' => 'INV-'.str_pad((string) $sale->id, 6, '0', STR_PAD_LEFT)]);

            $this->recordLines($sale, $basket->lines, $data, $cashier, $affectsStock);
            $this->recordPayments($sale, $data);

            if ($due > 0 && $sale->customer_id) {
                Customer::whereKey($sale->customer_id)->increment('balance', $due);
            }

            $this->awardLoyalty($sale, $cashier, $affectsStock);

            if ($basket->hasOverride || $discount > 0) {
                $this->audit->log('sale.price_override', $sale, null, [
                    'discount_amount' => $discount,
                    'lines' => array_map(static fn (PricedLine $l) => $l->toItemAttributes(), $basket->lines),
                ], $cashier);
            }

            return $sale->load('items.taxes', 'payments', 'customer', 'coupon');
        });
    }

    /**
     * Records a payment against an existing due/partial sale (e.g. a customer
     * settling a credit sale later). Row-locks the sale so two concurrent
     * payments can never together overpay the outstanding balance.
     */
    public function collectPayment(Sale $sale, CollectPaymentData $data): Sale
    {
        return DB::transaction(function () use ($sale, $data) {
            /** @var Sale $sale */
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== SaleStatus::Completed) {
                throw ValidationException::withMessages([
                    'sale' => ['Payments can only be recorded against a completed sale.'],
                ]);
            }

            $due = round((float) $sale->due_amount, 2);

            if ($due <= 0) {
                throw ValidationException::withMessages([
                    'amount' => ['This sale has no outstanding balance.'],
                ]);
            }

            if ($data->amount > $due) {
                throw ValidationException::withMessages([
                    'amount' => ["The payment amount may not exceed the outstanding balance ({$due})."],
                ]);
            }

            $sale->payments()->create([
                'method' => $data->method,
                'amount' => $data->amount,
                'reference' => $data->reference,
                'paid_at' => Carbon::now(),
            ]);

            $newDue = round($due - $data->amount, 2);

            $sale->update([
                'paid_amount' => round((float) $sale->paid_amount + $data->amount, 2),
                'due_amount' => $newDue,
                'payment_status' => $newDue <= 0 ? PaymentStatus::Paid : PaymentStatus::Partial,
            ]);

            if ($sale->customer_id) {
                Customer::whereKey($sale->customer_id)->decrement('balance', $data->amount);
            }

            return $sale->fresh(['items.taxes', 'payments', 'customer', 'coupon']);
        });
    }

    /**
     * Finalises a held cart: moves the stock it was deliberately deferring,
     * records any tender taken at the till now, and completes the sale. The
     * lines and their tax were already priced and snapshotted when the cart
     * was held — resuming never re-runs the pricing or tax engine, so a menu
     * or rate change while a cart sat held cannot change what the customer
     * was quoted.
     *
     * @param  array<int, PaymentData>  $payments
     */
    public function resume(Sale $sale, array $payments, User $cashier): Sale
    {
        return DB::transaction(function () use ($sale, $payments, $cashier) {
            /** @var Sale $sale */
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== SaleStatus::Held && $sale->status !== SaleStatus::Draft) {
                throw ValidationException::withMessages([
                    'status' => ['Only a held or draft sale can be resumed.'],
                ]);
            }

            $sale->load('items.product');

            foreach ($sale->items as $item) {
                if ($item->product?->track_inventory ?? true) {
                    $this->ledger->record([
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'warehouse_id' => $sale->warehouse_id,
                        'quantity' => (float) $item->quantity,
                        'unit_cost' => (float) $item->unit_cost,
                        'type' => StockMovementType::Sale,
                        'reference' => $sale,
                        'user_id' => $cashier->id,
                    ]);
                }
            }

            foreach ($payments as $payment) {
                $sale->payments()->create([
                    'method' => $payment->method,
                    'amount' => $payment->amount,
                    'reference' => $payment->reference,
                    'paid_at' => Carbon::now(),
                ]);
            }

            $total = round((float) $sale->total, 2);
            $paid = round((float) $sale->paid_amount + PaymentData::total($payments), 2);
            $change = max(0, round($paid - $total, 2));
            $due = max(0, round($total - $paid, 2));

            $sale->update([
                'status' => SaleStatus::Completed,
                'payment_status' => PaymentStatus::fromAmounts($paid, $total),
                'paid_amount' => min($paid, $total),
                'change_amount' => $change,
                'due_amount' => $due,
                'sold_at' => Carbon::now(),
            ]);

            if ($due > 0 && $sale->customer_id) {
                Customer::whereKey($sale->customer_id)->increment('balance', $due);
            }

            $this->awardLoyalty($sale, $cashier, true);

            return $sale->fresh(['items.taxes', 'payments', 'customer', 'coupon']);
        });
    }

    /**
     * Cancels a completed sale: reverses the stock it moved and, if the
     * customer owed anything on it, clears that receivable. Cash already
     * collected is not auto-refunded — voiding corrects a mistaken sale, it
     * is not a refund workflow (that is {@see SaleReturnService}).
     */
    public function void(Sale $sale, User $user, ?string $reason = null): Sale
    {
        return DB::transaction(function () use ($sale, $user, $reason) {
            /** @var Sale $sale */
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== SaleStatus::Completed) {
                throw ValidationException::withMessages([
                    'status' => ['Only a completed sale can be voided.'],
                ]);
            }

            $sale->load('items.product');

            foreach ($sale->items as $item) {
                if ($item->product?->track_inventory ?? true) {
                    $this->ledger->record([
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'warehouse_id' => $sale->warehouse_id,
                        'quantity' => (float) $item->quantity,
                        'unit_cost' => (float) $item->unit_cost,
                        'type' => StockMovementType::AdjustmentIn,
                        'reference' => $sale,
                        'user_id' => $user->id,
                    ]);
                }
            }

            $due = round((float) $sale->due_amount, 2);
            if ($due > 0 && $sale->customer_id) {
                Customer::whereKey($sale->customer_id)->decrement('balance', $due);
            }

            $sale->update(['status' => SaleStatus::Cancelled, 'due_amount' => 0]);

            $this->audit->log('sale.void', $sale, null, ['reason' => $reason], $user);

            return $sale->fresh(['items.taxes', 'payments', 'customer']);
        });
    }

    /** Renders a sale as a printable/downloadable invoice PDF. */
    public function invoicePdf(Sale $sale): PdfDocument
    {
        $sale->loadMissing(['items.taxes', 'payments', 'customer', 'cashier', 'branch.business']);
        $business = $sale->branch->business;

        return Pdf::loadView('pdf.invoice', [
            'sale' => $sale,
            'branch' => $sale->branch,
            'business' => $business,
            // A profile-required field (e.g. GSTIN) and the label it's shown
            // under vary by jurisdiction — the document must reflect whichever
            // profile this business actually trades under, not a hardcoded one.
            'profile' => $business->effectiveTaxProfile(),
        ]);
    }

    /**
     * A sale-level discount is permission-gated and can never exceed the subtotal
     * — otherwise the total goes negative and the sale pays the customer.
     */
    private function resolveSaleDiscount(CreateSaleData $data, float $subtotal, User $cashier): float
    {
        if ($data->discountAmount <= 0) {
            return 0.0;
        }

        if (! $cashier->hasPermission('pos.discount')) {
            throw new AuthorizationException('Missing required permission: pos.discount.');
        }

        if ($data->discountAmount > round($subtotal, 2)) {
            throw ValidationException::withMessages([
                'discount_amount' => ['The discount amount may not exceed the sale subtotal.'],
            ]);
        }

        return $data->discountAmount;
    }

    /**
     * The jurisdiction and pricing rules for this sale. Resolved once per
     * document — every line on one invoice is taxed under the same regime — from
     * the selling branch (origin sourcing) and the customer (destination
     * sourcing, and exemptions).
     */
    private function taxContextFor(CreateSaleData $data, User $cashier): TaxContext
    {
        return $this->taxes->contextFor(
            $cashier->business ?? Business::findOrFail($cashier->business_id),
            Branch::find($data->branchId),
            $data->customerId !== null ? Customer::find($data->customerId) : null,
        );
    }

    /** @param  array<int, PricedLine>  $lines */
    private function recordLines(Sale $sale, array $lines, CreateSaleData $data, User $cashier, bool $affectsStock): void
    {
        foreach ($lines as $line) {
            $item = $sale->items()->create($line->toItemAttributes());

            // The per-component split is what an invoice prints, a return
            // reverses, and a VAT/GST return sums — none of which can be
            // recovered from the single `tax_amount` column afterwards.
            foreach ($line->taxComponents as $component) {
                $item->taxes()->create($component->toAttributes());
            }

            if ($affectsStock && $line->tracksInventory) {
                $this->ledger->record([
                    'product_id' => $line->productId,
                    'variant_id' => $line->variantId,
                    'warehouse_id' => $data->warehouseId,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unitCost,
                    'type' => StockMovementType::Sale,
                    'reference' => $sale,
                    'user_id' => $cashier->id,
                ]);
            }
        }
    }

    private function recordPayments(Sale $sale, CreateSaleData $data): void
    {
        foreach ($data->payments as $payment) {
            $sale->payments()->create([
                'method' => $payment->method,
                'amount' => $payment->amount,
                'reference' => $payment->reference,
                'paid_at' => Carbon::now(),
            ]);
        }
    }

    /**
     * Award loyalty points on a completed sale to the attached customer, using the
     * business's `loyalty_earn_rate` setting (points per currency unit). Disabled
     * (no points) when the rate is unset/zero, so it is opt-in per business.
     */
    private function awardLoyalty(Sale $sale, User $cashier, bool $completed): void
    {
        if (! $completed || ! $sale->customer_id) {
            return;
        }

        $rate = (float) data_get($cashier->business?->settings, 'loyalty_earn_rate', 0);

        if ($rate <= 0) {
            return;
        }

        $points = (int) floor((float) $sale->total * $rate);

        if ($points > 0 && ($customer = Customer::find($sale->customer_id))) {
            app(LoyaltyService::class)
                ->earn($customer, $points, $sale->id, $cashier->id, "Earned on {$sale->invoice_number}");
        }
    }
}
