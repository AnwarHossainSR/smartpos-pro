<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class StockMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
            ]),
            'variant_id' => $this->variant_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'type' => $this->type,
            'direction' => $this->direction,
            'quantity' => (float) $this->quantity,
            'quantity_after' => (float) $this->quantity_after,
            'unit_cost' => (float) $this->unit_cost,
            'reference_type' => $this->reference_type ? Str::afterLast($this->reference_type, '\\') : null,
            'reference_id' => $this->reference_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? ['id' => $this->user->id, 'name' => $this->user->name] : null),
            'note' => $this->note,
            'created_at' => $this->created_at,
        ];
    }
}
