<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreTaxRateRequest;
use App\Http\Resources\TaxRateResource;
use App\Models\TaxRate;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $rates = QueryPipeline::for(TaxRate::query(), $request)
            ->search(['name', 'code'])
            ->allow(
                AllowedFilter::boolean('is_active'),
                AllowedFilter::exact('type'),
            )
            ->sortable(['name', 'rate', 'created_at'], default: 'name')
            ->paginate();

        return TaxRateResource::collection($rates);
    }

    public function store(StoreTaxRateRequest $request): TaxRateResource
    {
        return new TaxRateResource(TaxRate::create($request->validated()));
    }

    public function update(StoreTaxRateRequest $request, TaxRate $taxRate): TaxRateResource
    {
        $taxRate->update($request->validated());

        return new TaxRateResource($taxRate);
    }

    public function destroy(TaxRate $taxRate)
    {
        $taxRate->delete();

        return response()->noContent();
    }
}
