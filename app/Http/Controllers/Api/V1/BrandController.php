<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $brands = QueryPipeline::for(Brand::query()->withCount('products'), $request)
            ->search(['name'])
            ->allow(AllowedFilter::boolean('is_active'))
            ->sortable(['name', 'created_at'], default: 'name')
            ->paginate();

        return BrandResource::collection($brands);
    }

    public function store(StoreBrandRequest $request): BrandResource
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));

        return new BrandResource(Brand::create($data));
    }

    public function update(StoreBrandRequest $request, Brand $brand): BrandResource
    {
        $brand->update($request->validated());

        return new BrandResource($brand);
    }

    public function destroy(Brand $brand)
    {
        $brand->delete();

        return response()->noContent();
    }
}
