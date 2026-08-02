<?php

namespace App\Domain\Tax;

use App\Support\Money;

/**
 * The rounded, reconciled tax position of a whole document.
 *
 * The invariant this type exists to guarantee: the per-line component amounts
 * sum exactly to the per-component totals, which sum exactly to `taxTotal`. No
 * caller has to re-add anything and discover a stray minor unit.
 */
final readonly class TaxBreakdown
{
    /**
     * @param  array<int, RoundedLineTax>  $lines  keyed by the caller's line index
     * @param  array<int, TaxComponentTotal>  $componentTotals
     */
    public function __construct(
        public array $lines,
        public array $componentTotals,
        public Money $taxTotal,
    ) {}

    public function lineFor(int $index): RoundedLineTax
    {
        return $this->lines[$index] ?? RoundedLineTax::empty($this->taxTotal->currency);
    }

    public function isZero(): bool
    {
        return $this->taxTotal->isZero();
    }
}
