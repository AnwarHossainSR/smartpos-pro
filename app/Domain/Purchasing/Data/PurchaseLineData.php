<?php

namespace App\Domain\Purchasing\Data;

/** One line on a goods receipt. Cost is supplier-supplied, unlike a sale's price. */
final readonly class PurchaseLineData
{
    public function __construct(
        public int $productId,
        public float $quantity,
        public float $unitCost,
        public ?int $variantId = null,
        public float $discountAmount = 0.0,
        public float $taxAmount = 0.0,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['product_id'],
            quantity: round((float) $data['quantity'], 4),
            unitCost: round((float) $data['unit_cost'], 4),
            variantId: isset($data['variant_id']) ? (int) $data['variant_id'] : null,
            discountAmount: round((float) ($data['discount_amount'] ?? 0), 2),
            taxAmount: round((float) ($data['tax_amount'] ?? 0), 2),
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
