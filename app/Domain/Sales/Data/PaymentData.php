<?php

namespace App\Domain\Sales\Data;

use App\Domain\Enums\PaymentMethod;

/** One tender against a sale or purchase. Several may settle a single document. */
final readonly class PaymentData
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

    /**
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<int, self>
     */
    public static function collection(array $payments): array
    {
        return array_map(static fn (array $payment) => self::fromArray($payment), $payments);
    }

    /** @param  array<int, self>  $payments */
    public static function total(array $payments): float
    {
        return round(array_sum(array_map(static fn (self $p) => $p->amount, $payments)), 2);
    }
}
