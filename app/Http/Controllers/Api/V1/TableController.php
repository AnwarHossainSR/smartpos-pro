<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Table\StoreTableRequest;
use App\Http\Requests\Api\V1\Table\UpdateTableRequest;
use App\Http\Resources\Api\V1\TableResource;
use App\Models\Table;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Dining tables & floor plan (T125).
 *
 * Reads are tenant-scoped and `tenant_id` is auto-filled by BelongsToTenant, so
 * neither needs doing here. Authorisation is the `permission:tables.*` middleware
 * on the routes.
 */
class TableController extends Controller
{
    use ApiResponse;

    public function index(Request $request): AnonymousResourceCollection
    {
        $tables = Table::query()
            ->with('branch')
            ->when($request->query('branch_id'), fn ($q, $branchId) => $q->where('branch_id', $branchId))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('name')
            ->get();

        return TableResource::collection($tables);
    }

    public function store(StoreTableRequest $request): TableResource
    {
        return new TableResource(Table::create($request->validated()));
    }

    public function show(Table $table): TableResource
    {
        return new TableResource($table->load('branch'));
    }

    public function update(UpdateTableRequest $request, Table $table): TableResource
    {
        $table->update($request->validated());

        return new TableResource($table->fresh());
    }

    public function destroy(Table $table): Response
    {
        $table->delete();

        return response()->noContent();
    }
}
