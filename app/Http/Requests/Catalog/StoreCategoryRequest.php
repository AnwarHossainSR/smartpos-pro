<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
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
            'parent_id' => ['nullable', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }
}
