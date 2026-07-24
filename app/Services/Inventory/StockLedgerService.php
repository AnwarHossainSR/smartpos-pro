<?php

namespace App\Services\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Models\InventoryBalance;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * The single choke point for every stock change in the system.
 *
 * Guarantees:
 *   - Balances are mutated under a pessimistic row lock (SELECT ... FOR UPDATE),
 *     preventing lost updates / oversell under concurrency.
 *   - Every change appends an immutable row to stock_movements (the ledger),
 *     recording the running balance so history is fully auditable.
 *   - Weighted-average cost is recomputed on inbound movements.
 *
 * Callers MUST invoke this inside a database transaction so the balance update
 * and the ledger insert commit atomically.
 */
class StockLedgerService
{
    /**
     * @param  array{
     *   product_id:int, warehouse_id:int, direction:'in'|'out', quantity:float,
     *   type:string, variant_id?:int|null, unit_cost?:float, user_id?:int|null,
     *   note?:string|null, reference?:Model|null, allow_negative?:bool
     * }  $data
     */
    public function record(array $data): StockMovement
    {
        $direction = $data['direction'];
        $quantity = round((float) $data['quantity'], 4);
        $unitCost = round((float) ($data['unit_cost'] ?? 0), 4);
        $variantId = $data['variant_id'] ?? null;

        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Stock movement quantity must be positive.');
        }

        $balance = $this->lockBalance($data['product_id'], $variantId, $data['warehouse_id']);

        $current = (float) $balance->quantity;

        if ($direction === 'out') {
            $available = $current - (float) $balance->reserved_quantity;
            if (! ($data['allow_negative'] ?? false) && $available < $quantity) {
                throw new InsufficientStockException(
                    $data['product_id'], $data['warehouse_id'], $available, $quantity
                );
            }
            $newQuantity = $current - $quantity;
        } else {
            $newQuantity = $current + $quantity;
            // Weighted-average cost, recomputed only on inbound stock with a known cost.
            if ($unitCost > 0 && $newQuantity > 0) {
                $balance->average_cost = round(
                    (($current * (float) $balance->average_cost) + ($quantity * $unitCost)) / $newQuantity,
                    4
                );
            }
        }

        $balance->quantity = $newQuantity;
        $balance->save();

        $reference = $data['reference'] ?? null;

        return StockMovement::create([
            'tenant_id' => $balance->tenant_id,
            'product_id' => $data['product_id'],
            'variant_id' => $variantId,
            'warehouse_id' => $data['warehouse_id'],
            'type' => $data['type'],
            'direction' => $direction,
            'quantity' => $quantity,
            'quantity_after' => $newQuantity,
            'unit_cost' => $unitCost ?: (float) $balance->average_cost,
            'reference_type' => $reference ? $reference->getMorphClass() : null,
            'reference_id' => $reference?->getKey(),
            'user_id' => $data['user_id'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
    }

    /** Fetch (or create) the balance row and hold a write lock on it. */
    protected function lockBalance(int $productId, ?int $variantId, int $warehouseId): InventoryBalance
    {
        $query = fn () => InventoryBalance::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->when($variantId, fn ($q) => $q->where('variant_id', $variantId), fn ($q) => $q->whereNull('variant_id'))
            ->lockForUpdate()
            ->first();

        if ($balance = $query()) {
            return $balance;
        }

        InventoryBalance::firstOrCreate(
            ['product_id' => $productId, 'variant_id' => $variantId, 'warehouse_id' => $warehouseId],
            ['quantity' => 0, 'reserved_quantity' => 0, 'average_cost' => 0]
        );

        // Re-select under lock now that the row is guaranteed to exist.
        return $query();
    }

    /** Convenience helper used by seeders and opening-stock flows. */
    public function addOpeningStock(int $productId, int $warehouseId, float $quantity, float $unitCost = 0): StockMovement
    {
        return DB::transaction(fn () => $this->record([
            'product_id' => $productId,
            'warehouse_id' => $warehouseId,
            'direction' => 'in',
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'type' => 'opening',
        ]));
    }
}
