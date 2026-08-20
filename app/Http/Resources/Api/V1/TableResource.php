<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TableResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'area' => $this->area,
            'seats' => $this->seats,
            'status' => $this->status,
            'x' => $this->x,
            'y' => $this->y,
            'has_active_sale' => $this->active_sale_id !== null,
        ];
    }
}