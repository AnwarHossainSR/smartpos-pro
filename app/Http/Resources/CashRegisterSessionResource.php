<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'cash_register_id' => $this->cash_register_id,
            'user_id' => $this->user_id,
            'opening_cash' => (float) $this->opening_cash,
            'cash_in' => (float) $this->cash_in,
            'cash_out' => (float) $this->cash_out,
            'expected_cash' => (float) $this->expected_cash,
            'actual_cash' => $this->actual_cash !== null ? (float) $this->actual_cash : null,
            'difference' => $this->difference !== null ? (float) $this->difference : null,
            'status' => $this->status,
            'note' => $this->note,
            'opened_at' => $this->opened_at,
            'closed_at' => $this->closed_at,
            'transactions' => $this->whenLoaded('transactions', fn () => $this->transactions->map(fn ($t) => [
                'type' => $t->type,
                'amount' => (float) $t->amount,
                'reason' => $t->reason,
                'created_at' => $t->created_at,
            ])),
        ];
    }
}
