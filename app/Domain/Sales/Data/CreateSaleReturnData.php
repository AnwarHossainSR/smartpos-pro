<?php

namespace App\Domain\Sales\Data;

use App\Domain\Enums\RefundMethod;

final readonly class CreateSaleReturnData
{
    /** @param  array<int, SaleReturnLineData>  $items */
    public function __construct(
        public array $items,
        public ?int $warehouseId = null,
        public RefundMethod $refundMethod = RefundMethod::Cash,
        public ?string $note = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            items: array_map(
                static fn (array $item) => SaleReturnLineData::fromArray($item),
                $data['items'],
            ),
            warehouseId: isset($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
            refundMethod: RefundMethod::coerce($data['refund_method'] ?? null, RefundMethod::Cash),
            note: $data['note'] ?? null,
        );
    }
}
