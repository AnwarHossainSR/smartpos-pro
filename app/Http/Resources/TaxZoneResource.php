<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_code' => $this->country_code,
            'state' => $this->state,
            'postcode_pattern' => $this->postcode_pattern,
            'tax_group_id' => $this->tax_group_id,
            'tax_group' => new TaxGroupResource($this->whenLoaded('taxGroup')),
            'is_default' => (bool) $this->is_default,
            'priority' => $this->priority,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
