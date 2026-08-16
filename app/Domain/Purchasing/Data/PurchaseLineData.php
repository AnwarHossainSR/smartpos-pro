<?php

namespace App\Domain\Purchasing\Data;

use App\Domain\Tax\TaxEngine;

/**
 * One line on a goods receipt. Cost is supplier-supplied, unlike a sale's
 * price. Tax is not: input tax is resolved from the product's tax group by
 * the {@see TaxEngine}, the same way a sale's is, so a client
 * cannot understate what was reclaimed.
 */
final readonly class PurchaseLineData
{
    public function __construct(
        public string $productId,
        public float $quantity,
        public float $unitCost,
        public ?string $variantId = null,
        public float $discountAmount = 0.0,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: $data['product_id'],
            quantity: round((float) $data['quantity'], 4),
            unitCost: round((float) $data['unit_cost'], 4),
            variantId: $data['variant_id'] ?? null,
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
}
