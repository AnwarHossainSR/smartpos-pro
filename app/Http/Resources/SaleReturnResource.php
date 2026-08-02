<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleReturnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'sale_id' => $this->sale_id,
            'branch_id' => $this->branch_id,
            'warehouse_id' => $this->warehouse_id,
            'customer_id' => $this->customer_id,
            'subtotal' => (float) $this->subtotal,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'refund_method' => $this->refund_method,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'sale_item_id' => $i->sale_item_id,
                'product_id' => $i->product_id,
                'quantity' => (float) $i->quantity,
                'unit_price' => (float) $i->unit_price,
                'line_total' => (float) $i->line_total,
                'tax_amount' => (float) $i->taxes->sum('amount'),
                'taxes' => $i->taxes->map(fn ($t) => [
                    'name' => $t->name, 'rate' => (float) $t->rate, 'amount' => (float) $t->amount,
                ]),
            ])),
            'note' => $this->note,
            'returned_at' => $this->returned_at,
        ];
    }
}
