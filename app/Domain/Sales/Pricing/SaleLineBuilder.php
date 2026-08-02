<?php

namespace App\Domain\Sales\Pricing;

use App\Domain\Sales\Data\SaleLineData;
use App\Domain\Tax\TaxableLine;
use App\Domain\Tax\TaxContext;
use App\Domain\Tax\TaxEngine;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Prices a basket against the live catalogue and hands it to the tax engine.
 *
 * The client never sets a price: it may *request* one, and the request is only
 * honoured when the cashier holds the matching permission. Everything else —
 * cost, name, and the whole tax position — is derived server-side and
 * snapshotted onto the line.
 *
 * Whether the quoted price includes tax is a tenant setting, so the same basket
 * is priced differently in a VAT-inclusive market (the shelf price is the gross,
 * and tax is extracted from it) than in a net-quoting one (tax is added on top).
 * That decision lives in {@see TaxContext}, not here.
 */
class SaleLineBuilder
{
    public function __construct(protected TaxEngine $tax) {}

    /** @param  array<int, SaleLineData>  $items */
    public function build(array $items, User $cashier, TaxContext $context): PricedBasket
    {
        $products = Product::whereIn('id', array_map(static fn (SaleLineData $i) => $i->productId, $items))
            ->with(TaxEngine::PRODUCT_RELATIONS)
            ->get()
            ->keyBy('id');

        $resolved = [];
        $taxable = [];
        $hasOverride = false;

        foreach ($items as $index => $item) {
            /** @var Product|null $product */
            $product = $products->get($item->productId);

            if (! $product) {
                throw new InvalidArgumentException("Product #{$item->productId} does not exist in this tenant.");
            }

            $catalogPrice = round((float) $product->selling_price, 2);

            if ($item->requestsPriceOverride($catalogPrice)) {
                $this->authorize($cashier, 'pos.price_override');
                $hasOverride = true;
            }

            if ($item->discountAmount > 0) {
                $this->authorize($cashier, 'pos.discount');
                $hasOverride = true;
            }

            $unitPrice = $item->unitPrice !== null ? round($item->unitPrice, 2) : $catalogPrice;
            $quantity = round($item->quantity, 4);

            // The taxable base is the discounted line, never the gross one.
            $amount = max(0, ($unitPrice * $quantity) - $item->discountAmount);

            $resolved[$index] = ['product' => $product, 'item' => $item, 'unitPrice' => $unitPrice, 'quantity' => $quantity];
            $taxable[$index] = new TaxableLine($amount, $quantity, $product);
        }

        $breakdown = $this->tax->taxDocument($taxable, $context);

        $lines = [];

        foreach ($resolved as $index => $r) {
            $lineTax = $breakdown->lineFor($index);

            $lines[] = new PricedLine(
                productId: $r['product']->id,
                variantId: $r['item']->variantId,
                name: $r['product']->name,
                quantity: $r['quantity'],
                unitPrice: $r['unitPrice'],
                unitCost: (float) $r['product']->purchase_price,
                discountAmount: $r['item']->discountAmount,
                taxRate: $lineTax->effectiveRate(),
                taxAmount: $lineTax->tax->toFloat(),
                lineTotal: $lineTax->gross()->toFloat(),
                netTotal: $lineTax->net->toFloat(),
                tracksInventory: (bool) $r['product']->track_inventory,
                taxComponents: $lineTax->components,
            );
        }

        return new PricedBasket($lines, $hasOverride, $breakdown);
    }

    private function authorize(User $cashier, string $permission): void
    {
        if (! $cashier->hasPermission($permission)) {
            throw new AuthorizationException("Missing required permission: {$permission}.");
        }
    }
}
