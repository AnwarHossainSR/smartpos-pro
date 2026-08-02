<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'barcode' => $this->barcode,
            'type' => $this->type,
            'description' => $this->description,
            'image_path' => $this->image_path,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'brand' => $this->whenLoaded('brand', fn () => ['id' => $this->brand?->id, 'name' => $this->brand?->name]),
            'purchase_price' => (float) $this->purchase_price,
            'selling_price' => (float) $this->selling_price,
            'wholesale_price' => $this->wholesale_price !== null ? (float) $this->wholesale_price : null,
            'tax_group_id' => $this->tax_group_id,
            'tax_group' => new TaxGroupResource($this->whenLoaded('taxGroup')),
            'min_stock_level' => (float) $this->min_stock_level,
            'track_inventory' => (bool) $this->track_inventory,
            'is_active' => (bool) $this->is_active,
            'stock' => $this->when(isset($this->stock_quantity), fn () => (float) $this->stock_quantity),
            'tags' => $this->tags ?? [],
            'created_at' => $this->created_at,
        ];
    }
}
