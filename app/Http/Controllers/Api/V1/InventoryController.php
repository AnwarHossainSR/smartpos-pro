<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\InventoryBalanceResource;
use App\Http\Resources\StockMovementResource;
use App\Models\InventoryBalance;
use App\Models\StockMovement;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function __construct(protected StockLedgerService $ledger) {}

    /** Live stock balances across warehouses, with search + low-stock filter. */
    public function index(Request $request)
    {
        $balances = InventoryBalance::query()
            ->with(['product', 'warehouse'])
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->search, fn ($q, $s) => $q->whereHas('product', fn ($p) => $p->where('name', 'like', "%{$s}%")->orWhere('sku', 'like', "%{$s}%")))
            ->when($request->boolean('low_stock'), fn ($q) => $q->whereColumn('quantity', '<=', DB::raw('(select min_stock_level from products where products.id = inventory_balances.product_id)')))
            ->orderByDesc('quantity')
            ->paginate($this->perPage($request, 25));

        return InventoryBalanceResource::collection($balances);
    }

    /** Read-only history of the immutable stock ledger, filterable by product/warehouse/type/date. */
    public function movements(Request $request)
    {
        $movements = StockMovement::query()
            ->with(['product:id,name,sku', 'warehouse:id,name', 'user:id,name'])
            ->when($request->product_id, fn ($q, $id) => $q->where('product_id', $id))
            ->when($request->warehouse_id, fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->variant_id, fn ($q, $id) => $q->where('variant_id', $id))
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->direction, fn ($q, $d) => $q->where('direction', $d))
            ->tap(fn ($q) => $this->filterByDateRange($q, $request, 'created_at'))
            ->latest('id')
            ->paginate($this->perPage($request, 25));

        return StockMovementResource::collection($movements);
    }

    /** Manual stock adjustment (in/out), recorded through the immutable ledger. */
    public function adjust(Request $request)
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $movement = DB::transaction(fn () => $this->ledger->record([
            'product_id' => (int) $data['product_id'],
            'warehouse_id' => (int) $data['warehouse_id'],
            'direction' => $data['direction'],
            'quantity' => (float) $data['quantity'],
            'unit_cost' => (float) ($data['unit_cost'] ?? 0),
            'type' => $data['direction'] === 'in' ? 'adjustment_in' : 'adjustment_out',
            'user_id' => $request->user()->id,
            'note' => $data['note'] ?? null,
        ]));

        return response()->json([
            'message' => 'Stock adjusted.',
            'quantity_after' => (float) $movement->quantity_after,
        ], 201);
    }
}
