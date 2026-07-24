<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BrandController extends Controller
{
    public function index(Request $request)
    {
        $brands = Brand::query()
            ->withCount('products')
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->when($request->filled('is_active'), fn ($q) => $q->where('is_active', $request->boolean('is_active')))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 25));

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
