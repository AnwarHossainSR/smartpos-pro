<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => new PlanResource($this->whenLoaded('plan')),
            'status' => $this->status,
            'trial_ends_at' => $this->trial_ends_at,
            'current_period_ends_at' => $this->current_period_ends_at,
            'created_at' => $this->created_at,
        ];
    }
}
