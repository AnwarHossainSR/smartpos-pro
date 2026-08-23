<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'plan' => ['sometimes', 'string', 'max:50'],
            'status' => ['sometimes', 'in:active,suspended'],
            'trial_ends_at' => ['sometimes', 'nullable', 'date'],
            'base_currency' => ['sometimes', 'string', 'size:3'],
            'feature_limits' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
