<?php

namespace App\Domain\Tax;

use App\Domain\Enums\TaxType;
use App\Support\Money;

/** One component's rounded contribution to one line — the row an invoice prints. */
final readonly class RoundedTaxComponent
{
    public function __construct(
        public ?string $rateId,
        public string $name,
        public float $rate,
        public TaxType $type,
        public bool $isCompound,
        public Money $baseAmount,
        public Money $amount,
    ) {}

    /** @return array<string, mixed> the *_item_taxes column set */
    public function toAttributes(): array
    {
        return [
            'tax_rate_id' => $this->rateId,
            'name' => $this->name,
            'rate' => $this->rate,
            'type' => $this->type,
            'is_compound' => $this->isCompound,
            'base_amount' => $this->baseAmount->toFloat(),
            'amount' => $this->amount->toFloat(),
        ];
    }
}
