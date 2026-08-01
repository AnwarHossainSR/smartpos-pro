<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A group's components are declared as an ordered array — `priority` here is
 * what makes a compound component (SGST-on-CGST, PST-on-GST) apply after the
 * ones it compounds on.
 */
class StoreTaxGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('tax_groups', 'name')->where('tenant_id', $tenantId)->ignore($this->route('taxGroup')),
            ],
            'code' => ['nullable', 'string', 'max:50'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'rates' => ['nullable', 'array'],
            'rates.*.tax_rate_id' => ['required', Rule::exists('tax_rates', 'id')->where('tenant_id', $tenantId)],
            'rates.*.priority' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
