<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The customer-facing view of a sale, addressed by its public token.
 *
 * Deliberately hand-listed rather than derived from `SaleResource`: this is the
 * one payload in the app served without authentication, so every field here is
 * one a stranger holding the link is allowed to read. No ids, no costs, no
 * cashier, no customer record — only what a paper receipt would already show.
 *
 * @property Sale $resource
 */
class OrderStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $sale = $this->resource;

        return [
            'invoice_number' => $sale->invoice_number,
            'status' => $sale->status,
            'payment_status' => $sale->payment_status,
            'sold_at' => $sale->sold_at?->toIso8601String(),
            'currency' => $sale->business?->currency,
            'subtotal' => (float) $sale->subtotal,
            'discount_amount' => (float) $sale->discount_amount + (float) $sale->coupon_discount,
            'tax_amount' => (float) $sale->tax_amount,
            'shipping_amount' => (float) $sale->shipping_amount,
            'total' => (float) $sale->total,
            'paid_amount' => (float) $sale->paid_amount,
            'due_amount' => (float) $sale->due_amount,
            'items' => $sale->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
            ])->all(),
            'seller' => [
                'name' => $sale->business?->name,
                'phone' => $sale->business?->phone,
                'email' => $sale->business?->email,
                'branch' => $sale->branch?->name,
            ],
        ];
    }
}
