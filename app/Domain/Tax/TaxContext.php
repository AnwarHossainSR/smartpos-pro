<?php

namespace App\Domain\Tax;

use App\Domain\Enums\PriceDisplay;
use App\Domain\Enums\RoundingMode;
use App\Domain\Enums\TaxRoundingScope;
use App\Models\TaxGroup;
use App\Models\TaxProfile;

/**
 * The tax rules in force for one transaction: which jurisdiction applies, how
 * prices are quoted, how to round, and whether this particular customer is
 * exempt. Resolved once per document rather than per line, because every line on
 * an invoice is taxed under the same regime.
 */
final readonly class TaxContext
{
    public function __construct(
        public TaxProfile $profile,
        public PriceDisplay $priceDisplay,
        public string $currency,
        public bool $customerExempt = false,
        /** The zone's default group, used when neither product nor category names one. */
        public ?TaxGroup $zoneGroup = null,
    ) {}

    public function roundingScope(): TaxRoundingScope
    {
        return $this->profile->rounding_scope;
    }

    public function roundingMode(): RoundingMode
    {
        return $this->profile->rounding_mode;
    }

    /** A tax-exempt customer short-circuits the whole engine. */
    public function taxable(): bool
    {
        return ! $this->customerExempt;
    }
}
