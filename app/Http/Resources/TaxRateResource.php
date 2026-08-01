<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxRateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'rate' => (float) $this->rate,
            'type' => $this->type,
            'is_compound' => (bool) $this->is_compound,
            'is_active' => (bool) $this->is_active,
            'priority' => $this->whenPivotLoaded('tax_group_rate', fn () => $this->pivot->priority),
            'created_at' => $this->created_at,
        ];
    }
}
