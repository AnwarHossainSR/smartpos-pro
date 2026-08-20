<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Table\StoreTableRequest;
use App\Http\Requests\Api\V1\Table\UpdateTableRequest;
use App\Http\Resources\Api\V1\TableResource;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TableController extends Controller
{
    public function index(Request $request)
    {
        Gate::allow('tables.view');

        $branchId = $request->query('branch_id');

        $query = Table::where('tenant_id', app('app\TenantContext')->id());

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $tables = $query->orderBy('name')->get();

        return TableResource::collection($tables);
    }

    public function store(StoreTableRequest $request)
    {
        Gate::allow('tables.create');

        $data = $request->validated();
        $data['tenant_id'] = app('app\TenantContext')->id();

        $table = Table::create($data);

        return new TableResource($table);
    }

    public function update(UpdateTableRequest $request, Table $table)
    {
        Gate::allow('tables.update');

        $data = $request->validated();
        $table->update($data);

        return new TableResource($table);
    }
}