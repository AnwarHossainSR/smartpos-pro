<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'variant_id' => $this->variant_id,
            'quantity' => (float) $this->quantity,
            'unit_cost' => (float) $this->unit_cost,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'line_total' => (float) $this->line_total,
        ];
    }
}
