<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $delivery = $this->resource;

        return [
            'id' => $delivery->id,
            'sale_id' => $delivery->sale_id,
            'zone_id' => $delivery->zone_id,
            'zone_name' => $delivery->zone?->name,
            'delivery_fee' => (float) $delivery->zone?->delivery_fee ?? 0,
            'status' => $delivery->status,
            'tracking_token' => $delivery->tracking_token,
            'created_at' => $delivery->created_at,
        ];
    }
}
