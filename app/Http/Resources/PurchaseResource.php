<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'status' => $this->status,
            'payment_status' => $this->payment_status,
            'warehouse_id' => $this->warehouse_id,
            'supplier' => new SupplierResource($this->whenLoaded('supplier')),
            'subtotal' => (float) $this->subtotal,
            'discount_amount' => (float) $this->discount_amount,
            'tax_amount' => (float) $this->tax_amount,
            'shipping_amount' => (float) $this->shipping_amount,
            'total' => (float) $this->total,
            'paid_amount' => (float) $this->paid_amount,
            'due_amount' => (float) $this->due_amount,
            'items' => PurchaseItemResource::collection($this->whenLoaded('items')),
            'note' => $this->note,
            'purchased_at' => $this->purchased_at,
            'created_at' => $this->created_at,
        ];
    }
}
