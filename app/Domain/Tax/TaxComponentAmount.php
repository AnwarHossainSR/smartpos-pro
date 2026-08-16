<?php

namespace App\Domain\Tax;

use App\Domain\Enums\TaxRoundingScope;
use App\Domain\Enums\TaxType;

/**
 * One tax component's contribution to one line, **unrounded**.
 *
 * Rounding is deliberately not applied here: which granularity to round at is a
 * jurisdiction rule ({@see TaxRoundingScope}), so the
 * calculator produces exact figures and {@see TaxRounder} decides where the
 * fractions land. Rounding twice — once here and once at the total — is how a
 * basket ends up a paisa short of its own lines.
 */
final readonly class TaxComponentAmount
{
    public function __construct(
        public ?string $rateId,
        public string $name,
        public float $rate,
        public TaxType $type,
        public bool $isCompound,
        /** What this component was charged on — the net line, or the running total when compound. */
        public float $baseAmount,
        public float $amount,
    ) {}

    /** Components are grouped across an invoice by identity, not by name alone. */
    public function key(): string
    {
        return ($this->rateId !== null ? "id:{$this->rateId}" : "name:{$this->name}")
            .":{$this->rate}:{$this->type->value}";
    }

    public function withAmount(float $amount): self
    {
        return new self(
            $this->rateId,
            $this->name,
            $this->rate,
            $this->type,
            $this->isCompound,
            $this->baseAmount,
            $amount,
        );
    }
}
