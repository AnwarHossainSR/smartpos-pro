<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'branch_id' => $this->branch_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category?->id,
                'name' => $this->category?->name,
            ]),
            'amount' => (float) $this->amount,
            'payment_method' => $this->payment_method,
            'status' => $this->status,
            'note' => $this->note,
            'spent_at' => $this->spent_at?->toDateString(),
            'created_at' => $this->created_at,
        ];
    }
}
