<?php

namespace App\Events;

use App\Models\InventoryBalance;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when stock falls at or below a product's min_stock_level after an outbound movement.
 */
class InventoryLow implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly InventoryBalance $balance,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('tenant.'.$this->tenantId)];
    }

    public function broadcastAs(): string
    {
        return 'inventory.low';
    }

    public function broadcastWith(): array
    {
        return [
            'product_id' => $this->balance->product_id,
            'warehouse_id' => $this->balance->warehouse_id,
            'quantity' => (float) $this->balance->quantity,
        ];
    }
}
