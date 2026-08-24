<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $categories = QueryPipeline::for(Category::query()->withCount('products'), $request)
            ->search(['name'])
            ->allow(
                AllowedFilter::boolean('is_active'),
                AllowedFilter::exact('parent_id'),
            )
            ->sortable(['name', 'created_at'], default: 'name')
            ->paginate();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): CategoryResource
    {
        $data = $request->validated();
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));

        return new CategoryResource(Category::create($data));
    }

    public function update(StoreCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return new CategoryResource($category);
    }

    public function destroy(Category $category)
    {
        $category->delete();

        return response()->noContent();
    }
}
