<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreTaxZoneRequest;
use App\Http\Resources\TaxZoneResource;
use App\Models\TaxZone;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TaxZoneController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $zones = QueryPipeline::for(TaxZone::query()->with('taxGroup'), $request)
            ->search(['name', 'country_code', 'state'])
            ->allow(
                AllowedFilter::boolean('is_active'),
                AllowedFilter::exact('country_code'),
            )
            ->sortable(['name', 'priority', 'created_at'], default: 'priority')
            ->paginate();

        return TaxZoneResource::collection($zones);
    }

    public function store(StoreTaxZoneRequest $request): TaxZoneResource
    {
        return new TaxZoneResource(TaxZone::create($request->validated())->load('taxGroup'));
    }

    public function update(StoreTaxZoneRequest $request, TaxZone $taxZone): TaxZoneResource
    {
        $taxZone->update($request->validated());

        return new TaxZoneResource($taxZone->load('taxGroup'));
    }

    public function destroy(TaxZone $taxZone)
    {
        $taxZone->delete();

        return response()->noContent();
    }
}
