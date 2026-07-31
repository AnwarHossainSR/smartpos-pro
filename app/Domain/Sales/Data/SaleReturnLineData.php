<?php

namespace App\Domain\Sales\Data;

/** One line being returned, identified by the original sale item it reverses. */
final readonly class SaleReturnLineData
{
    public function __construct(
        public int $saleItemId,
        public float $quantity,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            saleItemId: (int) $data['sale_item_id'],
            quantity: round((float) $data['quantity'], 4),
        );
    }
}
