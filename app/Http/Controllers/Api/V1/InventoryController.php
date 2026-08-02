<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\StockDirection;
use App\Domain\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryBalanceResource;
use App\Http\Resources\StockMovementResource;
use App\Models\InventoryBalance;
use App\Models\StockMovement;
use App\Services\Inventory\StockLedgerService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(protected StockLedgerService $ledger) {}

    /** Live stock balances across warehouses, with search + low-stock filter. */
    public function index(Request $request)
    {
        $balances = QueryPipeline::for(InventoryBalance::query()->with(['product', 'warehouse']), $request)
            ->allow(
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('product_id'),
                AllowedFilter::searchRelation('search', 'product', ['name', 'sku']),
                AllowedFilter::flag('low_stock', fn (Builder $q) => $q->whereColumn(
                    'quantity',
                    '<=',
                    DB::raw('(select min_stock_level from products where products.id = inventory_balances.product_id)'),
                )),
            )
            ->sortable(['quantity', 'average_cost'], default: '-quantity')
            ->paginate();

        return InventoryBalanceResource::collection($balances);
    }

    /** Read-only history of the immutable stock ledger, filterable by product/warehouse/type/date. */
    public function movements(Request $request)
    {
        $movements = QueryPipeline::for(
            StockMovement::query()->with(['product:id,name,sku', 'warehouse:id,name', 'user:id,name']),
            $request,
        )
            ->allow(
                AllowedFilter::exact('product_id'),
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('variant_id'),
                AllowedFilter::enum('type', StockMovementType::class),
                AllowedFilter::enum('direction', StockDirection::class),
                AllowedFilter::dateRange('created_at'),
            )
            ->sortable(['id', 'created_at', 'quantity'], default: '-id')
            ->paginate();

        return StockMovementResource::collection($movements);
    }

    /** Manual stock adjustment (in/out), recorded through the immutable ledger. */
    public function adjust(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'direction' => ['required', StockDirection::rule()],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $type = StockDirection::from($data['direction']) === StockDirection::In
            ? StockMovementType::AdjustmentIn
            : StockMovementType::AdjustmentOut;

        $movement = DB::transaction(fn () => $this->ledger->record([
            'product_id' => (int) $data['product_id'],
            'warehouse_id' => (int) $data['warehouse_id'],
            'quantity' => (float) $data['quantity'],
            'unit_cost' => (float) ($data['unit_cost'] ?? 0),
            'type' => $type,
            'user_id' => $request->user()->id,
            'note' => $data['note'] ?? null,
        ]));

        return response()->json([
            'message' => 'Stock adjusted.',
            'quantity_after' => (float) $movement->quantity_after,
        ], 201);
    }
}
