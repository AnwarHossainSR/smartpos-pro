<?php

namespace App\Domain\Sales\Pricing;

/**
 * A checkout line after the server has priced it.
 *
 * Every money figure here is a *snapshot*: the product's price, cost and tax rate
 * as they stood at the moment of sale. Historical invoices must not change when
 * someone edits the catalogue, so nothing downstream re-reads the product.
 */
final readonly class PricedLine
{
    public function __construct(
        public int $productId,
        public ?int $variantId,
        public string $name,
        public float $quantity,
        public float $unitPrice,
        public float $unitCost,
        public float $discountAmount,
        public float $taxRate,
        public float $taxAmount,
        /** Line total as charged — includes tax. */
        public float $lineTotal,
        /** Line total excluding tax; this is what sums into the invoice subtotal. */
        public float $netTotal,
        public bool $tracksInventory,
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
