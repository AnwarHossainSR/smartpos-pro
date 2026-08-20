<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Stocktake;
use App\Services\Inventory\StocktakeService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StocktakeController extends Controller
{
    public function __construct(protected StocktakeService $service) {}

    public function index(Request $request): JsonResponse
    {
        $stocktakes = QueryPipeline::for(
            Stocktake::with(['warehouse', 'branch', 'countedBy']),
            $request,
        )
            ->search(['name'])
            ->allow(
                AllowedFilter::exact('warehouse_id'),
                AllowedFilter::exact('branch_id'),
                AllowedFilter::exact('status'),
            )
            ->sortable(['id', 'created_at', 'started_at', 'completed_at'], default: '-id')
            ->paginate(20);

        return response()->json($stocktakes);
    }

    public function store(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $stocktake = $this->service->createStocktake(
            $data['branch_id'],
            $data['warehouse_id'],
            $data['name'],
            $data['notes'] ?? null,
            $request->user(),
        );

        return response()->json(['data' => $stocktake], 201);
    }

    public function show(Stocktake $stocktake): JsonResponse
    {
        return response()->json([
            'data' => $stocktake->load([
                'items.product', 'items.variant', 'warehouse', 'branch', 'countedBy',
            ]),
        ]);
    }

    public function start(Stocktake $stocktake, Request $request): JsonResponse
    {
        $stocktake = $this->service->startCounting($stocktake->id, $request->user());

        return response()->json(['data' => $stocktake]);
    }

    public function recordCount(Stocktake $stocktake, string $item, Request $request): JsonResponse
    {
        $data = $request->validate([
            'counted_qty' => ['required', 'numeric', 'min:0'],
        ]);

        $item = $this->service->recordCount($item, (float) $data['counted_qty']);

        return response()->json(['data' => $item]);
    }

    public function complete(Stocktake $stocktake, Request $request): JsonResponse
    {
        $stocktake = $this->service->completeStocktake($stocktake->id, $request->user());

        return response()->json(['data' => $stocktake]);
    }

    public function cancel(Stocktake $stocktake): JsonResponse
    {
        $stocktake = $this->service->cancelStocktake($stocktake->id);

        return response()->json(['data' => $stocktake]);
    }
}
