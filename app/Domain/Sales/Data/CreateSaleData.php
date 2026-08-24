<?php

namespace App\Domain\Sales\Data;

use App\Domain\Enums\SaleStatus;

/**
 * Everything a checkout request asks for, typed.
 *
 * Replaces the `array $payload` the sale service used to take, whose shape lived
 * only in a docblock — so a renamed key was a runtime `Undefined array key`
 * rather than a static error, and the request, the service and the docblock
 * could all disagree without anything noticing.
 */
final readonly class CreateSaleData
{
    /**
     * @param  array<int, SaleLineData>  $items
     * @param  array<int, PaymentData>  $payments
     */
    public function __construct(
        public string $branchId,
        public string $warehouseId,
        public array $items,
        public array $payments = [],
        public ?string $customerId = null,
        public SaleStatus $status = SaleStatus::Completed,
        public ?string $note = null,
        public float $discountAmount = 0.0,
        public float $shippingAmount = 0.0,
        public ?string $couponCode = null,
        /** Single-use supervisor approval minted by `POST /pos/approvals`, when one is needed. */
        public ?string $approvalToken = null,
        public ?string $tenderCurrency = null,
        public float $tenderAmount = 0.0,
        public ?float $fxRate = null,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            branchId: $data['branch_id'],
            warehouseId: $data['warehouse_id'],
            items: SaleLineData::collection($data['items']),
            payments: PaymentData::collection($data['payments'] ?? []),
            customerId: $data['customer_id'] ?? null,
            status: SaleStatus::coerce($data['status'] ?? null, SaleStatus::Completed),
            note: $data['note'] ?? null,
            discountAmount: round((float) ($data['discount_amount'] ?? 0), 2),
            shippingAmount: round((float) ($data['shipping_amount'] ?? 0), 2),
            couponCode: ($data['coupon_code'] ?? null) ?: null,
            approvalToken: ($data['approval_token'] ?? null) ?: null,
            tenderCurrency: ($data['tender_currency'] ?? null) ?: null,
            tenderAmount: round((float) ($data['tender_amount'] ?? 0), 2),
            fxRate: isset($data['fx_rate']) ? (float) $data['fx_rate'] : null,
        );
    }

    public function paidAmount(): float
    {
        return PaymentData::total($this->payments);
    }
}
