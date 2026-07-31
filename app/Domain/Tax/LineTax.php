<?php

namespace App\Domain\Tax;

/** The unrounded tax position of a single line. */
final readonly class LineTax
{
    /** @param  array<int, TaxComponentAmount>  $components */
    public function __construct(
        /** Line value excluding tax. */
        public float $net,
        /** Line value including tax. */
        public float $gross,
        public array $components,
    ) {}

    public function taxTotal(): float
    {
        return array_sum(array_map(static fn (TaxComponentAmount $c) => $c->amount, $this->components));
    }

    public static function untaxed(float $net): self
    {
        return new self($net, $net, []);
    }
}
