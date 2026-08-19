<?php

namespace App\Domain\Sales\Pricing;

use App\Domain\Tax\TaxBreakdown;

/** The priced result of a whole basket, plus whether any line needed an override. */
final readonly class PricedBasket
{
    /** @param  array<int, PricedLine>  $lines */
    public function __construct(
        public array $lines,
        public bool $hasOverride,
        public ?TaxBreakdown $tax = null,
        /** True when at least one line was sold at a price other than the catalogue one. */
        public bool $hasPriceOverride = false,
        /** Sum of the explicit per-line discounts the client asked for. */
        public float $lineDiscountTotal = 0.0,
        /**
         * What the basket is worth at catalogue prices, before any discount or
         * override. This — not the discounted subtotal — is the denominator a
         * percentage discount limit is measured against, so pushing the unit
         * price up first cannot buy a bigger discount allowance.
         */
        public float $catalogGross = 0.0,
        /** How far below catalogue the honoured price overrides went, in currency. */
        public float $priceOverrideDiscount = 0.0,
    ) {}

    /**
     * Everything the cashier gave away on this basket that is not a server-computed
     * promotion: explicit line discounts plus the value of any downward price
     * override. A sale-level discount is added on top by the caller.
     */
    public function clientDiscountTotal(): float
    {
        return round($this->lineDiscountTotal + $this->priceOverrideDiscount, 2);
    }

    /** Sum of line totals excluding tax. */
    public function subtotal(): float
    {
        return round(array_sum(array_map(static fn (PricedLine $l) => $l->netTotal, $this->lines)), 2);
    }

    /**
     * Taken from the breakdown rather than re-summed from the lines: under
     * component- or invoice-scoped rounding the authoritative total is the one
     * the rounder produced, and re-adding rounded lines can differ from it.
     */
    public function taxTotal(): float
    {
        return $this->tax?->taxTotal->toFloat()
            ?? round(array_sum(array_map(static fn (PricedLine $l) => $l->taxAmount, $this->lines)), 2);
    }
}
