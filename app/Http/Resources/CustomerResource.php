<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_group' => $this->customer_group,
            'credit_limit' => (float) $this->credit_limit,
            'balance' => (float) $this->balance,
            'loyalty_points' => (int) $this->loyalty_points,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
