<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Sale;

class DeliveryService
{
    public function assignDelivery(Sale $sale, int $zoneId): Sale
    {
        $zone = DeliveryZone::findOrFail($zoneId);

        $delivery = Delivery::create([
            'sale_id' => $sale->id,
            'zone_id' => $zone->id,
            'status' => 'dispatched',
            'tracking_token' => $sale->public_token,
        ]);

        return $sale->load('delivery');
    }

    public function getDriverView(int $deliveryId): array
    {
        $delivery = Delivery::with(['sale', 'zone'])->findOrFail($deliveryId);

        return [
            'id' => $delivery->id,
            'sale_id' => $delivery->sale_id,
            'zone_id' => $delivery->zone_id,
            'zone_name' => $delivery->zone?->name,
            'delivery_fee' => $delivery->zone?->delivery_fee ?? 0,
            'status' => $delivery->status,
            'tracking_token' => $delivery->tracking_token,
            'created_at' => $delivery->created_at,
        ];
    }

    public function updateStatus(int $deliveryId, string $status): Delivery
    {
        $delivery = Delivery::findOrFail($deliveryId);

        $validStatuses = ['dispatched', 'in-transit', 'completed', 'cancelled'];

        if (! in_array($status, $validStatuses)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $delivery->status = $status;
        $delivery->save();

        return $delivery;
    }
}
