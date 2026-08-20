<?php

namespace App\Services\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryZone;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    public const STATUSES = ['dispatched', 'in-transit', 'completed', 'cancelled'];

    /**
     * Attach a delivery to a sale. The zone lookup is tenant-scoped by
     * BelongsToTenant, so a zone belonging to another tenant 404s rather than
     * leaking across the boundary.
     */
    public function assignDelivery(Sale $sale, int $zoneId): Sale
    {
        $zone = DeliveryZone::findOrFail($zoneId);

        DB::transaction(function () use ($sale, $zone): void {
            Delivery::create([
                'sale_id' => $sale->id,
                'zone_id' => $zone->id,
                'status' => 'dispatched',
                'tracking_token' => $sale->public_token,
            ]);
        });

        return $sale->load('delivery.zone');
    }

    public function updateStatus(Delivery $delivery, string $status): Delivery
    {
        if (! in_array($status, self::STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => ["Invalid delivery status: {$status}."],
            ]);
        }

        $delivery->update(['status' => $status]);

        return $delivery->fresh(['sale', 'zone']);
    }
}
