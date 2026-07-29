<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Support\Concerns\ValidatesSort;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ValidatesSort;

    protected const SORTABLE = ['name', 'phone', 'email', 'balance', 'created_at'];

    public function index(Request $request)
    {
        [$sort, $direction] = $this->resolveSort($request, self::SORTABLE, 'name');

        $customers = Customer::query()
            ->when($request->search, function ($q, $s) {
                $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%"));
            })
            ->when($request->filled('has_due'), fn ($q) => $q->where('balance', '>', 0))
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 25));

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): CustomerResource
    {
        $data = $request->validated();
        $data['balance'] = $data['opening_balance'] ?? 0;

        return new CustomerResource(Customer::create($data));
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer);
    }

    public function update(StoreCustomerRequest $request, Customer $customer): CustomerResource
    {
        $customer->update($request->validated());

        return new CustomerResource($customer);
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();

        return response()->noContent();
    }
}
