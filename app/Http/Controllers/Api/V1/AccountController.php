<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\AccountType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        $accounts = QueryPipeline::for(Account::query()->orderBy('type'), $request)
            ->search(['name', 'account_number'])
            ->allow(
                AllowedFilter::enum('type', AccountType::class),
                AllowedFilter::boolean('is_active'),
            )
            ->sortable(['name', 'balance'], default: 'name')
            ->paginate(50);

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request): AccountResource
    {
        $data = $request->validated();
        $data['balance'] = $data['opening_balance'] ?? 0;

        return new AccountResource(Account::create($data));
    }

    public function update(StoreAccountRequest $request, Account $account): AccountResource
    {
        $account->update($request->safe()->except('opening_balance'));

        return new AccountResource($account);
    }
}
