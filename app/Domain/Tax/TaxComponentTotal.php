<?php

namespace App\Domain\Tax;

use App\Support\Money;

/** A component's total across a document — the line a VAT/GST return sums. */
final readonly class TaxComponentTotal
{
    public function __construct(
        public ?string $rateId,
        public string $name,
        public float $rate,
        public Money $baseAmount,
        public Money $amount,
    ) {}
}
