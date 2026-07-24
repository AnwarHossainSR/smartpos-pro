<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreAccountRequest;
use App\Http\Resources\AccountResource;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request)
    {
        $accounts = Account::query()
            ->when($request->type, fn ($q, $t) => $q->where('type', $t))
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderBy('type')->orderBy('name')
            ->paginate($request->integer('per_page', 50));

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
