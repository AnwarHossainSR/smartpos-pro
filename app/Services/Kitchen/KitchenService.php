<?php

namespace App\Services\Kitchen;

use App\Events\KitchenOrderUpdated;
use App\Models\Sale;

class KitchenService
{
    public function updateStatus(Sale $sale, string $status, ?string $note = null): Sale
    {
        $sale->kitchen_status = $status;
        $sale->kitchen_note = $note;
        $sale->save();

        broadcast(new KitchenOrderUpdated($sale, $status, $note));

        return $sale;
    }

    public function pending(Sale $sale): Sale
    {
        return $this->updateStatus($sale, 'pending');
    }

    public function preparing(Sale $sale, ?string $note = null): Sale
    {
        return $this->updateStatus($sale, 'preparing', $note);
    }

    public function ready(Sale $sale): Sale
    {
        return $this->updateStatus($sale, 'ready');
    }

    public function served(Sale $sale): Sale
    {
        return $this->updateStatus($sale, 'served');
    }
}
