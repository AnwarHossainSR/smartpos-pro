<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'cashier' => $this->whenLoaded('cashier', fn () => ['id' => $this->cashier?->id, 'name' => $this->cashier?->name]),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'coupon_code' => $this->whenLoaded('coupon', fn () => $this->coupon?->code),
            'coupon_discount' => (float) $this->coupon_discount,
            'tax_amount' => (float) $this->tax_amount,
            'shipping_amount' => (float) $this->shipping_amount,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'change_amount' => (float) $this->change_amount,
            'due_amount' => (float) $this->due_amount,
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($p) => [
                'method' => $p->method,
                'amount' => (float) $p->amount,
                'reference' => $p->reference,
            ])),
            'note' => $this->note,
            'sold_at' => $this->sold_at,
            'created_at' => $this->created_at,
        ];
    }
}
