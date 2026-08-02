<?php

namespace App\Domain\Sales\Data;

use App\Domain\Enums\PaymentMethod;

/** A settlement payment recorded against an existing due or partially-paid sale. */
final readonly class CollectPaymentData
{
    public function __construct(
        public PaymentMethod $method,
        public float $amount,
        public ?string $reference = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            method: PaymentMethod::coerce($data['method'], PaymentMethod::Cash),
            amount: round((float) $data['amount'], 2),
            reference: $data['reference'] ?? null,
        );
    }
}
