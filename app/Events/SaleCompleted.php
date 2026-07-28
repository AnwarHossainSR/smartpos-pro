<?php

namespace App\Events;

use App\Models\Sale;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after a sale is committed to the database.
 * Broadcasts on a private tenant channel so the POS dashboard can
 * update sales totals and inventory in real time via Laravel Reverb.
 */
class SaleCompleted implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public readonly Sale $sale) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->sale->tenant_id),
            new PrivateChannel('branch.'.$this->sale->branch_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'sale.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->sale->id,
            'total' => (float) $this->sale->total,
            'payment_status' => $this->sale->payment_status,
            'sold_at' => $this->sale->sold_at,
        ];
    }
}
