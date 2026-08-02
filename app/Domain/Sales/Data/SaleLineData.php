<?php

namespace App\Domain\Sales\Data;

/**
 * One requested line on a checkout. This is what the *client asked for*, not what
 * the sale will be priced at — `unitPrice` is a request for an override that the
 * service only honours when the cashier holds `pos.price_override`.
 */
final readonly class SaleLineData
{
    public function __construct(
        public int $productId,
        public float $quantity,
        public ?int $variantId = null,
        public ?float $unitPrice = null,
        public float $discountAmount = 0.0,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            quantity: (float) $data['quantity'],
            variantId: isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            unitPrice: isset($data['unit_price']) ? (float) $data['unit_price'] : null,
            discountAmount: round((float) ($data['discount_amount'] ?? 0), 2),
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, self>
     */
    public static function collection(array $items): array
    {
        return array_map(static fn (array $item) => self::fromArray($item), $items);
    }

    /** True when the client asked for a price other than the catalogue one. */
    public function requestsPriceOverride(float $catalogPrice): bool
    {
        return $this->unitPrice !== null && round($this->unitPrice, 2) !== round($catalogPrice, 2);
    }
}
