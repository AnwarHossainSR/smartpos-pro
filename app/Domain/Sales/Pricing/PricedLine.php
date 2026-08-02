<?php

namespace App\Domain\Sales\Pricing;

use App\Domain\Tax\RoundedTaxComponent;

/**
 * A checkout line after the server has priced and taxed it.
 *
 * Every money figure here is a *snapshot*: the product's price, cost and the tax
 * components as they stood at the moment of sale. Historical invoices must not
 * change when someone edits the catalogue or re-rates a tax, so nothing
 * downstream re-reads the product or the tax group.
 */
final readonly class PricedLine
{
    /** @param  array<int, RoundedTaxComponent>  $taxComponents */
    public function __construct(
        public int $productId,
        public ?int $variantId,
        public string $name,
        public float $quantity,
        public float $unitPrice,
        public float $unitCost,
        public float $discountAmount,
        /** Blended effective rate, retained for the legacy `sale_items.tax_rate` column. */
        public float $taxRate,
        public float $taxAmount,
        /** Line total as charged — includes tax. */
        public float $lineTotal,
        /** Line total excluding tax; this is what sums into the invoice subtotal. */
        public float $netTotal,
        public bool $tracksInventory,
        public array $taxComponents = [],
    ) {}

    /** @return array<string, mixed> the sale_items column set */
    public function toItemAttributes(): array
    {
        return [
            'product_id' => $this->productId,
            'variant_id' => $this->variantId,
            'name' => $this->name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'unit_cost' => $this->unitCost,
            'discount_amount' => $this->discountAmount,
            'tax_rate' => $this->taxRate,
            'tax_amount' => $this->taxAmount,
            'line_total' => $this->lineTotal,
        ];
    }
}
