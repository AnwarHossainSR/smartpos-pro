<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            // Derived from `type` rather than the nullable `normal_side` column, so it
            // always matches the side posting actually credits/debits the balance on.
            'normal_side' => $this->type->normalSide(),
            'account_number' => $this->account_number,
            'opening_balance' => (float) $this->opening_balance,
            'balance' => (float) $this->balance,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
