<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['required', 'string', 'max:20'],
            'base_unit_id' => ['nullable', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'operator_value' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
