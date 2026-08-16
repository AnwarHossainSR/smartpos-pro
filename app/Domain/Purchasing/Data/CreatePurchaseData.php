<?php

namespace App\Domain\Purchasing\Data;

use App\Domain\Enums\PurchaseStatus;
use App\Domain\Sales\Data\PaymentData;

final readonly class CreatePurchaseData
{
    /**
     * @param  array<int, PurchaseLineData>  $items
     * @param  array<int, PaymentData>  $payments
     */
    public function __construct(
        public string $warehouseId,
        public array $items,
        public array $payments = [],
        public ?string $supplierId = null,
        public PurchaseStatus $status = PurchaseStatus::Received,
        public ?string $note = null,
        public float $discountAmount = 0.0,
        public float $shippingAmount = 0.0,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            warehouseId: $data['warehouse_id'],
            items: PurchaseLineData::collection($data['items']),
            payments: PaymentData::collection($data['payments'] ?? []),
            supplierId: $data['supplier_id'] ?? null,
            status: PurchaseStatus::coerce($data['status'] ?? null, PurchaseStatus::Received),
            note: $data['note'] ?? null,
            discountAmount: round((float) ($data['discount_amount'] ?? 0), 2),
            shippingAmount: round((float) ($data['shipping_amount'] ?? 0), 2),
        );
    }

    public function paidAmount(): float
    {
        return PaymentData::total($this->payments);
    }
}
