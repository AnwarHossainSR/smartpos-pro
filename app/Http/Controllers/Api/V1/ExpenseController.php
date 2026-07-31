<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $expenses = Expense::query()
            ->with('category')
            ->when($request->search, fn ($q, $s) => $q->where('reference', 'like', "%{$s}%")->orWhere('note', 'like', "%{$s}%"))
            ->when($request->expense_category_id, fn ($q, $id) => $q->where('expense_category_id', $id))
            ->when($request->branch_id, fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->tap(fn ($q) => $this->filterByDateRange($q, $request, 'spent_at'))
            ->latest('spent_at')
            ->paginate($this->perPage($request, 25));

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
        $expense->update(['status' => 'approved']);

        return new ExpenseResource($expense->load('category'));
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return response()->noContent();
    }
}
