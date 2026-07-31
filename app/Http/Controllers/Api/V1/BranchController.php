<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Organization\StoreBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = QueryPipeline::for(Branch::query()->withCount('warehouses')->orderByDesc('is_default'), $request)
            ->search(['name', 'code'])
            ->allow(AllowedFilter::boolean('is_active'))
            ->sortable(['name', 'code', 'created_at'], default: 'name')
            ->paginate();

        return BranchResource::collection($branches);
    }

    public function store(StoreBranchRequest $request): BranchResource
    {
        $data = $request->validated();
        $data['business_id'] = $request->user()->business_id;

        return new BranchResource(Branch::create($data));
    }

    public function show(Branch $branch): BranchResource
    {
        return new BranchResource($branch->loadCount('warehouses'));
    }

    public function update(StoreBranchRequest $request, Branch $branch): BranchResource
    {
        $branch->update($request->validated());

        return new BranchResource($branch->loadCount('warehouses'));
    }

    public function destroy(Branch $branch)
    {
        if ($branch->is_default) {
            throw ValidationException::withMessages(['branch' => 'The default branch cannot be deleted.']);
        }

        if ($branch->warehouses()->exists()) {
            throw ValidationException::withMessages(['branch' => 'Reassign or remove this branch\'s warehouses before deleting it.']);
        }

        if (User::where('branch_id', $branch->id)->exists()) {
            throw ValidationException::withMessages(['branch' => 'Reassign the team members on this branch before deleting it.']);
        }

        if (Sale::where('branch_id', $branch->id)->exists()) {
            throw ValidationException::withMessages(['branch' => 'This branch has sales history and cannot be deleted.']);
        }

        $branch->delete();

        return response()->noContent();
    }
}
