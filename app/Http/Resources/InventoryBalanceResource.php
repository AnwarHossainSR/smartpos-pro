<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventoryBalanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product' => $this->whenLoaded('product', fn () => [
                'id' => $this->product->id,
                'name' => $this->product->name,
                'sku' => $this->product->sku,
                'min_stock_level' => (float) $this->product->min_stock_level,
            ]),
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'quantity' => (float) $this->quantity,
            'reserved_quantity' => (float) $this->reserved_quantity,
            'available_quantity' => $this->availableQuantity(),
            'average_cost' => (float) $this->average_cost,
        ];
    }
}
