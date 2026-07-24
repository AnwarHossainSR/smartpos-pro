<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company' => $this->company,
            'email' => $this->email,
            'phone' => $this->phone,
            'tax_number' => $this->tax_number,
            'address' => $this->address,
            'opening_balance' => (float) $this->opening_balance,
            'balance' => (float) $this->balance,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
