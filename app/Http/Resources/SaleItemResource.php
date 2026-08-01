<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'name' => $this->name,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'tax_rate' => (float) $this->tax_rate,
            'taxes' => $this->whenLoaded('taxes', fn () => $this->taxes->map(fn ($t) => [
                'name' => $t->name, 'rate' => (float) $t->rate, 'amount' => (float) $t->amount,
            ])),
            'line_total' => (float) $this->line_total,
        ];
    }
}
