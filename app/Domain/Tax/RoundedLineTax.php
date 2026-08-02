<?php

namespace App\Domain\Tax;

use App\Support\Money;

/** One line's tax after rounding, with its per-component split preserved. */
final readonly class RoundedLineTax
{
    /** @param  array<int, RoundedTaxComponent>  $components */
    public function __construct(
        public Money $net,
        public Money $tax,
        public array $components,
    ) {}

    public function gross(): Money
    {
        return $this->net->plus($this->tax);
    }

    /** The blended percentage this line effectively paid, for the legacy `tax_rate` column. */
    public function effectiveRate(): float
    {
        if ($this->net->isZero()) {
            return 0.0;
        }

        return round(($this->tax->toFloat() / $this->net->toFloat()) * 100, 4);
    }

    public static function empty(string $currency): self
    {
        return new self(Money::zero($currency), Money::zero($currency), []);
    }
}
