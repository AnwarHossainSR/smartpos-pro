<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:cash,bank,mobile_banking,wallet,income,expense,asset,liability,equity'],
            'account_number' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['nullable', 'numeric'],
            'is_active' => ['boolean'],
        ];
    }
}
