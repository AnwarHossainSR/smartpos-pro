<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'entry_date' => $this->entry_date?->toDateString(),
            'description' => $this->description,
            'total_debit' => (float) $this->total_debit,
            'total_credit' => (float) $this->total_credit,
            'lines' => $this->whenLoaded('lines', fn () => $this->lines->map(fn ($l) => [
                'account_id' => $l->account_id,
                'type' => $l->type,
                'amount' => (float) $l->amount,
                'description' => $l->description,
            ])),
            'created_at' => $this->created_at,
        ];
    }
}
