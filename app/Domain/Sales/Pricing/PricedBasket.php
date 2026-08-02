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
    ) {}

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
