<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Support\Query\AllowedFilter;
use App\Support\Query\QueryPipeline;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = QueryPipeline::for(Expense::query()->with('category'), $request)
            ->search(['reference', 'note'])
            ->allow(
                AllowedFilter::exact('expense_category_id'),
                AllowedFilter::exact('branch_id'),
                AllowedFilter::enum('status', ExpenseStatus::class),
                AllowedFilter::dateRange('spent_at'),
            )
            ->sortable(['spent_at', 'amount', 'created_at'], default: '-spent_at')
            ->paginate();

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request): ExpenseResource
    {
        $data = $request->validated();
        $data['user_id'] = $request->user()->id;
        $data['status'] = 'approved';

        return new ExpenseResource(Expense::create($data)->load('category'));
    }

    public function update(StoreExpenseRequest $request, Expense $expense): ExpenseResource
    {
        $expense->update($request->validated());

        return new ExpenseResource($expense->load('category'));
    }

    public function approve(Expense $expense): ExpenseResource
    {
        $expense->update(['status' => ExpenseStatus::Approved]);

        return new ExpenseResource($expense->load('category'));
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return response()->noContent();
    }
}
