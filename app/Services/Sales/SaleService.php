<?php

namespace App\Services\Sales;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Product;
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
 * Orchestrates POS checkout. The entire sale — header, line items, stock
 * decrements and payments — is created inside one database transaction so a
 * failure anywhere (e.g. insufficient stock on the last line) rolls the whole
 * sale back and never leaves partial inventory or orphan records behind.
 */
class SaleService
{
    public function __construct(protected StockLedgerService $ledger, protected AuditLogService $audit) {}

    /**
     * @param  array{
     *   branch_id:int, warehouse_id:int, customer_id?:int|null, status?:string,
     *   note?:string|null, discount_amount?:float, shipping_amount?:float, coupon_code?:string|null,
     *   items: array<int, array{product_id:int, quantity:float, unit_price?:float, variant_id?:int|null, discount_amount?:float}>,
     *   payments?: array<int, array{method:string, amount:float, reference?:string|null}>
     * }  $payload
     */
    public function create(array $payload, User $cashier): Sale
    {
        return DB::transaction(function () use ($payload, $cashier) {
            $status = $payload['status'] ?? 'completed';
            $affectsStock = $status === 'completed';

            [$lines, $overridden] = $this->buildLines($payload['items'], $cashier);

            $subtotal = array_sum(array_column($lines, 'line_total'));
            $taxTotal = array_sum(array_column($lines, 'tax_amount'));
            $discount = round((float) ($payload['discount_amount'] ?? 0), 2);
            $shipping = round((float) ($payload['shipping_amount'] ?? 0), 2);

            if ($discount > 0 && ! $cashier->hasPermission('pos.discount')) {
                throw new AuthorizationException('Missing required permission: pos.discount.');
            }

            if ($discount > round($subtotal, 2)) {
                throw ValidationException::withMessages([
                    'discount_amount' => ['The discount amount may not exceed the sale subtotal.'],
                ]);
            }

            if ($discount > 0) {
                $overridden = true;
            }

            $coupon = null;
            $couponDiscount = 0.0;

            if (! empty($payload['coupon_code'])) {
                [$coupon, $couponDiscount] = $this->redeemCoupon($payload['coupon_code'], round($subtotal, 2));
            }

            $total = max(0, round($subtotal + $taxTotal + $shipping - $discount - $couponDiscount, 2));

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
                'coupon_id' => $coupon?->id,
                'invoice_number' => 'PENDING-'.uniqid(),
                'status' => $status,
                'payment_status' => $due <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'due'),
                'subtotal' => round($subtotal, 2),
                'discount_amount' => $discount,
                'coupon_discount' => round($couponDiscount, 2),
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

            $this->awardLoyalty($sale, $cashier, $affectsStock);

            if ($overridden) {
                $this->audit->log('sale.price_override', $sale, null, [
                    'discount_amount' => $discount,
                    'lines' => array_column($lines, 'attributes'),
                ], $cashier);
            }

            return $sale->load('items', 'payments', 'customer', 'coupon');
        });
    }

    /**
     * Records a payment against an existing due/partial sale (e.g. a customer
     * settling a credit sale later). Row-locks the sale so two concurrent
     * payments can never together overpay the outstanding balance.
     *
     * @param  array{method:string, amount:float, reference?:string|null}  $payload
     */
    public function collectPayment(Sale $sale, array $payload): Sale
    {
        return DB::transaction(function () use ($sale, $payload) {
            /** @var Sale $sale */
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status !== 'completed') {
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

            $amount = round((float) $payload['amount'], 2);

            if ($amount > $due) {
                throw ValidationException::withMessages([
                    'amount' => ["The payment amount may not exceed the outstanding balance ({$due})."],
                ]);
            }

            $sale->payments()->create([
                'method' => $payload['method'],
                'amount' => $amount,
                'reference' => $payload['reference'] ?? null,
                'paid_at' => Carbon::now(),
            ]);

            $newDue = round($due - $amount, 2);

            $sale->update([
                'paid_amount' => round((float) $sale->paid_amount + $amount, 2),
                'due_amount' => $newDue,
                'payment_status' => $newDue <= 0 ? 'paid' : 'partial',
            ]);

            if ($sale->customer_id) {
                Customer::whereKey($sale->customer_id)->decrement('balance', $amount);
            }

            return $sale->fresh(['items', 'payments', 'customer', 'coupon']);
        });
    }

    /**
     * Re-validates the coupon inside the checkout transaction and atomically
     * consumes one redemption. Row-locked so two concurrent checkouts against
     * a usage_limit-1 coupon can never both succeed.
     *
     * @return array{0: Coupon, 1: float}
     */
    protected function redeemCoupon(string $code, float $subtotal): array
    {
        $coupon = Coupon::where('code', strtoupper($code))->lockForUpdate()->first();

        if (! $coupon) {
            throw ValidationException::withMessages(['coupon_code' => ['Coupon not found.']]);
        }

        $result = $coupon->evaluate($subtotal);

        if (! $result['valid']) {
            throw ValidationException::withMessages(['coupon_code' => [$result['reason']]]);
        }

        $coupon->increment('used_count');

        return [$coupon, (float) $result['discount']];
    }

    /** Renders a sale as a printable/downloadable invoice PDF. */
    public function invoicePdf(Sale $sale): PdfDocument
    {
        $sale->loadMissing(['items', 'payments', 'customer', 'cashier', 'branch.business']);

        return Pdf::loadView('pdf.invoice', [
            'sale' => $sale,
            'branch' => $sale->branch,
            'business' => $sale->branch->business,
        ]);
    }

    /**
     * Award loyalty points on a completed sale to the attached customer, using the
     * business's `loyalty_earn_rate` setting (points per currency unit). Disabled
     * (no points) when the rate is unset/zero, so it is opt-in per business.
     */
    protected function awardLoyalty(Sale $sale, User $cashier, bool $completed): void
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

    /**
     * Resolve each requested line against the live product record, snapshotting
     * price/cost/tax so later product edits never mutate historical invoices.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array{0: array<int, array{attributes: array<string, mixed>, track_inventory: bool, line_total: float, tax_amount: float}>, 1: bool}
     */
    protected function buildLines(array $items, User $cashier): array
    {
        $productIds = collect($items)->pluck('product_id')->unique();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        $lines = [];
        $overridden = false;

        foreach ($items as $item) {
            /** @var Product $product */
            $product = $products->get($item['product_id']);

            if (! $product) {
                throw new \InvalidArgumentException("Product #{$item['product_id']} does not exist in this tenant.");
            }

            $quantity = round((float) $item['quantity'], 4);
            $catalogPrice = round((float) $product->selling_price, 2);
            $requestedPrice = array_key_exists('unit_price', $item) && $item['unit_price'] !== null
                ? round((float) $item['unit_price'], 2)
                : null;
            $lineDiscount = round((float) ($item['discount_amount'] ?? 0), 2);

            if ($requestedPrice !== null && $requestedPrice !== $catalogPrice) {
                if (! $cashier->hasPermission('pos.price_override')) {
                    throw new AuthorizationException('Missing required permission: pos.price_override.');
                }
                $overridden = true;
            }

            if ($lineDiscount > 0) {
                if (! $cashier->hasPermission('pos.discount')) {
                    throw new AuthorizationException('Missing required permission: pos.discount.');
                }
                $overridden = true;
            }

            $unitPrice = $requestedPrice ?? $catalogPrice;
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

        return [$lines, $overridden];
    }
}
