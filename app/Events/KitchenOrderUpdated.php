<?php

namespace App\Events;

use Illuminate\Broadcasting\BroadcastArray;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PreservesEvents;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class KitchenOrderUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, PreservesEvents;

    public $sale;

    public $status;

    public $note;

    public function __construct($sale, $status, $note = null)
    {
        $this->sale = $sale;
        $this->status = $status;
        $this->note = $note;
    }

    public function broadcastOn()
    {
        return new Channel('branch.'.$this->sale->branch_id);
    }

    public function broadcastWith()
    {
        return new BroadcastArray([
            'status' => $this->status,
            'sale_id' => $this->sale->id,
            'invoice_number' => $this->sale->invoice_number,
            'kitchen_note' => $this->note,
        ]);
    }
}
