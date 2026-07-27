<?php

namespace App\Services\Inventory;

use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockTransferService
{
    public function __construct(protected StockLedgerService $ledger) {}

    /**
     * Create a pending transfer request.
     *
     * @param  array{
     *   from_warehouse_id:int, to_warehouse_id:int, note?:string,
     *   items: array<int, array{product_id:int, variant_id?:int|null, quantity:float, unit_cost?:float}>
     * }  $payload
     */
    public function request(array $payload, User $user): StockTransfer
    {
        if ($payload['from_warehouse_id'] === $payload['to_warehouse_id']) {
            throw ValidationException::withMessages(['to_warehouse_id' => 'Source and destination warehouses must differ.']);
        }

        return DB::transaction(function () use ($payload, $user) {
            $transfer = StockTransfer::create([
                'tenant_id' => $user->tenant_id,
                'reference_number' => 'TRF-'.strtoupper(uniqid()),
                'from_warehouse_id' => $payload['from_warehouse_id'],
                'to_warehouse_id' => $payload['to_warehouse_id'],
                'requested_by' => $user->id,
                'status' => 'pending',
                'note' => $payload['note'] ?? null,
                'requested_at' => now(),
            ]);

            foreach ($payload['items'] as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'] ?? null,
                    'quantity_requested' => $item['quantity'],
                    'quantity_received' => 0,
                    'unit_cost' => $item['unit_cost'] ?? 0,
                ]);
            }

            return $transfer->load(['items.product', 'fromWarehouse', 'toWarehouse']);
        });
    }

    /**
     * Approve a pending transfer. Stock moves out of source warehouse immediately.
     */
    public function approve(StockTransfer $transfer, User $user): StockTransfer
    {
        if ($transfer->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only pending transfers can be approved.']);
        }

        return DB::transaction(function () use ($transfer, $user) {
            foreach ($transfer->items as $item) {
                $this->ledger->record([
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'direction' => 'out',
                    'quantity' => (float) $item->quantity_requested,
                    'type' => 'transfer_out',
                    'unit_cost' => (float) $item->unit_cost,
                    'user_id' => $user->id,
                    'note' => "Transfer {$transfer->reference_number}: dispatched",
                    'reference' => $transfer,
                ]);
            }

            $transfer->update([
                'status' => 'in_transit',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            return $transfer->fresh(['items.product', 'fromWarehouse', 'toWarehouse']);
        });
    }

    /**
     * Receive a transfer at the destination warehouse.
     *
     * @param  array{items: array<int, array{transfer_item_id:int, quantity_received:float}>}  $payload
     */
    public function receive(StockTransfer $transfer, array $payload, User $user): StockTransfer
    {
        if ($transfer->status !== 'in_transit') {
            throw ValidationException::withMessages(['status' => 'Only in-transit transfers can be received.']);
        }

        return DB::transaction(function () use ($transfer, $payload, $user) {
            $itemMap = collect($payload['items'])->keyBy('transfer_item_id');

            foreach ($transfer->items as $item) {
                $received = (float) ($itemMap[$item->id]['quantity_received'] ?? $item->quantity_requested);

                if ($received > 0) {
                    $this->ledger->record([
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'warehouse_id' => $transfer->to_warehouse_id,
                        'direction' => 'in',
                        'quantity' => $received,
                        'type' => 'transfer_in',
                        'unit_cost' => (float) $item->unit_cost,
                        'user_id' => $user->id,
                        'note' => "Transfer {$transfer->reference_number}: received",
                        'reference' => $transfer,
                    ]);

                    $item->update(['quantity_received' => $received]);
                }
            }

            $transfer->update([
                'status' => 'received',
                'received_by' => $user->id,
                'received_at' => now(),
            ]);

            return $transfer->fresh(['items.product', 'fromWarehouse', 'toWarehouse']);
        });
    }

    public function cancel(StockTransfer $transfer, User $user): StockTransfer
    {
        if (! in_array($transfer->status, ['pending'])) {
            throw ValidationException::withMessages(['status' => 'Only pending transfers can be cancelled.']);
        }

        $transfer->update(['status' => 'cancelled']);

        return $transfer;
    }
}
