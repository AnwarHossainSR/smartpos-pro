<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryResourceCollection extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection->map(fn ($delivery) => new DeliveryResource($delivery)),
            'meta' => [
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'total' => $this->total(),
            ],
        ];
    }
}
