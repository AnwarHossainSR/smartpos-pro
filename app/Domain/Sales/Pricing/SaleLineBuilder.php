<?php

namespace App\Domain\Sales\Pricing;

use App\Domain\Sales\Data\SaleLineData;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * Prices a basket against the live catalogue.
 *
 * The client never sets a price: it may *request* one, and the request is only
 * honoured when the cashier holds the matching permission. Everything else —
 * cost, tax rate, name — is read from the product and snapshotted onto the line,
 * so a later catalogue edit cannot rewrite history.
 */
class SaleLineBuilder
{
    /** @param  array<int, SaleLineData>  $items */
    public function build(array $items, User $cashier): PricedBasket
    {
        $products = Product::whereIn('id', array_map(static fn (SaleLineData $i) => $i->productId, $items))
            ->get()
            ->keyBy('id');

        $lines = [];
        $hasOverride = false;

        foreach ($items as $item) {
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

            $lines[] = $this->price($product, $item, $catalogPrice);
        }

        return new PricedBasket($lines, $hasOverride);
    }

    private function price(Product $product, SaleLineData $item, float $catalogPrice): PricedLine
    {
        $quantity = round($item->quantity, 4);
        $unitPrice = $item->unitPrice !== null ? round($item->unitPrice, 2) : $catalogPrice;

        // The taxable base is the discounted line, never the gross one.
        $taxable = max(0, ($unitPrice * $quantity) - $item->discountAmount);
        $rate = (float) $product->tax_rate;

        // An inclusive price already contains its tax, so the tax is extracted from
        // the line rather than added on top — otherwise the shelf price and the
        // charged price disagree.
        $taxAmount = $product->tax_inclusive
            ? round($taxable - ($taxable / (1 + ($rate / 100))), 2)
            : round($taxable * ($rate / 100), 2);

        return new PricedLine(
            productId: $product->id,
            variantId: $item->variantId,
            name: $product->name,
            quantity: $quantity,
            unitPrice: $unitPrice,
            unitCost: (float) $product->purchase_price,
            discountAmount: $item->discountAmount,
            taxRate: $rate,
            taxAmount: $taxAmount,
            lineTotal: round($product->tax_inclusive ? $taxable : $taxable + $taxAmount, 2),
            netTotal: round($product->tax_inclusive ? $taxable - $taxAmount : $taxable, 2),
            tracksInventory: (bool) $product->track_inventory,
        );
    }

    private function authorize(User $cashier, string $permission): void
    {
        if (! $cashier->hasPermission($permission)) {
            throw new AuthorizationException("Missing required permission: {$permission}.");
        }
    }
}
