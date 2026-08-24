<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreTaxGroupRequest;
use App\Http\Resources\TaxGroupResource;
use App\Models\TaxGroup;
use App\Services\TaxGroupService;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TaxGroupController extends Controller
{
    use ApiResponse;

    public function __construct(
        private TaxGroupService $taxGroups
    ) {}

    public function index(Request $request)
    {
        $groups = QueryPipeline::for(TaxGroup::query()->with('rates')->withCount('products'), $request)
            ->search(['name', 'code'])
            ->allow(AllowedFilter::boolean('is_active'))
            ->sortable(['name', 'created_at'], default: 'name')
            ->paginate();

        return TaxGroupResource::collection($groups);
    }

    public function store(StoreTaxGroupRequest $request): TaxGroupResource
    {
        $group = $this->taxGroups->create($request->validated());

        return new TaxGroupResource($group);
    }

    public function update(StoreTaxGroupRequest $request, TaxGroup $taxGroup): TaxGroupResource
    {
        $group = $this->taxGroups->update($taxGroup, $request->validated());

        return new TaxGroupResource($group);
    }

    public function destroy(TaxGroup $taxGroup)
    {
        $taxGroup->delete();

        return response()->noContent();
    }
}
