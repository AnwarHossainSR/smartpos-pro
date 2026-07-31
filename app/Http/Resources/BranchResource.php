<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'warehouses_count' => $this->whenCounted('warehouses'),
            'created_at' => $this->created_at,
        ];
    }
}
