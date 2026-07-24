<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'reference' => ['nullable', 'string', 'max:100'],
            'entry_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.account_id' => ['required', Rule::exists('accounts', 'id')->where('tenant_id', $tenantId)],
            'lines.*.type' => ['required', 'in:debit,credit'],
            'lines.*.amount' => ['required', 'numeric', 'gt:0'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
