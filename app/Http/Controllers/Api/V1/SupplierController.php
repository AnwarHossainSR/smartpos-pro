<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\StoreSupplierRequest;
use App\Http\Resources\SupplierResource;
use App\Models\Supplier;
use App\Support\Concerns\ValidatesSort;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    use ValidatesSort;

    protected const SORTABLE = ['name', 'company', 'phone', 'balance', 'created_at'];

    public function index(Request $request)
    {
        [$sort, $direction] = $this->resolveSort($request, self::SORTABLE, 'name');

        $suppliers = Supplier::query()
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('company', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%"));
            })
            ->when($request->filled('has_due'), fn ($q) => $q->where('balance', '>', 0))
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 25));

        return SupplierResource::collection($suppliers);
    }

    public function store(StoreSupplierRequest $request): SupplierResource
    {
        $data = $request->validated();
        $data['balance'] = $data['opening_balance'] ?? 0;

        return new SupplierResource(Supplier::create($data));
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): SupplierResource
    {
        $supplier->update($request->validated());

        return new SupplierResource($supplier);
    }

    public function destroy(Supplier $supplier)
    {
        $supplier->delete();

        return response()->noContent();
    }
}
