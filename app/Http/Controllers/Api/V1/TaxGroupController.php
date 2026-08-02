<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Tax\StoreTaxGroupRequest;
use App\Http\Resources\TaxGroupResource;
use App\Models\TaxGroup;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TaxGroupController extends Controller
{
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
        $data = $request->validated();

        $group = DB::transaction(function () use ($data) {
            $group = TaxGroup::create(collect($data)->except('rates')->all());
            $this->syncRates($group, $data['rates'] ?? []);

            return $group;
        });

        return new TaxGroupResource($group->load('rates'));
    }

    public function update(StoreTaxGroupRequest $request, TaxGroup $taxGroup): TaxGroupResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($taxGroup, $data) {
            $taxGroup->update(collect($data)->except('rates')->all());

            if (array_key_exists('rates', $data)) {
                $this->syncRates($taxGroup, $data['rates'] ?? []);
            }
        });

        return new TaxGroupResource($taxGroup->load('rates'));
    }

    public function destroy(TaxGroup $taxGroup)
    {
        $taxGroup->delete();

        return response()->noContent();
    }

    /** @param  array<int, array{tax_rate_id:int, priority?:int}>  $rates */
    private function syncRates(TaxGroup $group, array $rates): void
    {
        $pivot = [];

        foreach ($rates as $index => $rate) {
            $pivot[$rate['tax_rate_id']] = [
                'priority' => $rate['priority'] ?? $index,
                'tenant_id' => $group->tenant_id,
            ];
        }

        $group->rates()->sync($pivot);
    }
}
