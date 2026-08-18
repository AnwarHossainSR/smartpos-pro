<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookDeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'webhook_id' => $this->webhook_id,
            'event' => $this->event,
            'payload' => $this->payload,
            'response_code' => $this->response_code,
            'response_body' => $this->response_body,
            'delivered_at' => $this->delivered_at?->toISOString(),
            'attempts' => $this->attempts,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
