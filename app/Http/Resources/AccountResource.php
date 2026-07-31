<?php

namespace App\Http\Resources;

use App\Services\Accounting\JournalService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->type;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $type,
            // Derived from `type` rather than the nullable `normal_side` column, so it
            // always matches the side posting actually credits/debits the balance on.
            'normal_side' => JournalService::isDebitNormal($type) ? 'debit' : 'credit',
            'account_number' => $this->account_number,
            'opening_balance' => (float) $this->opening_balance,
            'balance' => (float) $this->balance,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
