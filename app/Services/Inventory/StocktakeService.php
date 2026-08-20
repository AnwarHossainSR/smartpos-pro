<?php

namespace App\Services\Inventory;

use App\Domain\Enums\StockMovementType;
use App\Domain\Enums\StocktakeStatus;
use App\Models\InventoryBalance;
use App\Models\Stocktake;
use App\Models\StocktakeItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StocktakeService
{
    public function __construct(protected StockLedgerService $ledger) {}

    public function createStocktake(string $branchId, string $warehouseId, string $name, ?string $notes, User $user): Stocktake
    {
        return Stocktake::create([
            'tenant_id' => $user->tenant_id,
            'branch_id' => $branchId,
            'warehouse_id' => $warehouseId,
            'name' => $name,
            'status' => StocktakeStatus::Draft,
            'notes' => $notes,
        ]);
    }

    public function startCounting(string $stocktakeId, User $user): Stocktake
    {
        $stocktake = Stocktake::findOrFail($stocktakeId);

        if ($stocktake->status !== StocktakeStatus::Draft) {
            throw ValidationException::withMessages(['status' => 'Only draft stocktakes can be started.']);
        }

        return DB::transaction(function () use ($stocktake, $user) {
            $balances = InventoryBalance::where('warehouse_id', $stocktake->warehouse_id)
                ->where('quantity', '>', 0)
                ->get();

            foreach ($balances as $balance) {
                StocktakeItem::create([
                    'stocktake_id' => $stocktake->id,
                    'product_id' => $balance->product_id,
                    'variant_id' => $balance->variant_id,
                    'system_qty' => $balance->quantity,
                ]);
            }

            $stocktake->update([
                'status' => StocktakeStatus::Counting,
                'counted_by' => $user->id,
                'started_at' => now(),
            ]);

            return $stocktake->fresh('items.product');
        });
    }

    public function recordCount(string $stocktakeItemId, float $countedQty): StocktakeItem
    {
        $item = StocktakeItem::findOrFail($stocktakeItemId);

        if ($item->stocktake->status !== StocktakeStatus::Counting) {
            throw ValidationException::withMessages(['status' => 'This stocktake is not in counting status.']);
        }

        $item->update([
            'counted_qty' => $countedQty,
            'variance' => $countedQty - $item->system_qty,
        ]);

        return $item->fresh('product');
    }

    public function completeStocktake(string $stocktakeId, User $user): Stocktake
    {
        $stocktake = Stocktake::with('items')->findOrFail($stocktakeId);

        if ($stocktake->status !== StocktakeStatus::Counting) {
            throw ValidationException::withMessages(['status' => 'Only counting stocktakes can be completed.']);
        }

        return DB::transaction(function () use ($stocktake, $user) {
            foreach ($stocktake->items as $item) {
                if ($item->counted_qty === null) {
                    continue;
                }

                $variance = (float) $item->variance;

                if ($variance > 0) {
                    $this->ledger->record([
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'warehouse_id' => $stocktake->warehouse_id,
                        'quantity' => abs($variance),
                        'type' => StockMovementType::AdjustmentIn,
                        'user_id' => $user->id,
                        'note' => "Stocktake adjustment: {$stocktake->name}",
                        'reference' => $stocktake,
                    ]);
                } elseif ($variance < 0) {
                    $this->ledger->record([
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                        'warehouse_id' => $stocktake->warehouse_id,
                        'quantity' => abs($variance),
                        'type' => StockMovementType::AdjustmentOut,
                        'user_id' => $user->id,
                        'note' => "Stocktake adjustment: {$stocktake->name}",
                        'reference' => $stocktake,
                    ]);
                }
            }

            $stocktake->update([
                'status' => StocktakeStatus::Completed,
                'completed_at' => now(),
            ]);

            return $stocktake->fresh(['items.product', 'warehouse', 'branch', 'countedBy']);
        });
    }

    public function cancelStocktake(string $stocktakeId): Stocktake
    {
        $stocktake = Stocktake::findOrFail($stocktakeId);

        if (! in_array($stocktake->status, [StocktakeStatus::Draft, StocktakeStatus::Counting], true)) {
            throw ValidationException::withMessages(['status' => 'Only draft or counting stocktakes can be cancelled.']);
        }

        $stocktake->update(['status' => StocktakeStatus::Cancelled]);

        return $stocktake;
    }
}
