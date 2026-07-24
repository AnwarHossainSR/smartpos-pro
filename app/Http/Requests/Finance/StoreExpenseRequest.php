<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'expense_category_id' => ['nullable', Rule::exists('expense_categories', 'id')->where('tenant_id', $tenantId)],
            'reference' => ['nullable', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['nullable', 'in:cash,card,bank,mobile_banking,wallet'],
            'note' => ['nullable', 'string', 'max:1000'],
            'spent_at' => ['required', 'date'],
        ];
    }
}
