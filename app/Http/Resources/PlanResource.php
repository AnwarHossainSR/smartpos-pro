<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price' => (float) $this->price,
            'interval' => $this->interval,
            'limits' => $this->limits,
            'features' => $this->features ?? [],
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
