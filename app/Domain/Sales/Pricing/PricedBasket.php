<?php

namespace App\Domain\Sales\Pricing;

/** The priced result of a whole basket, plus whether any line needed an override. */
final readonly class PricedBasket
{
    /** @param  array<int, PricedLine>  $lines */
    public function __construct(
        public array $lines,
        public bool $hasOverride,
    ) {}

    /** Sum of line totals excluding tax. */
    public function subtotal(): float
    {
        return round(array_sum(array_map(static fn (PricedLine $l) => $l->netTotal, $this->lines)), 2);
    }

    public function taxTotal(): float
    {
        return round(array_sum(array_map(static fn (PricedLine $l) => $l->taxAmount, $this->lines)), 2);
    }
}
