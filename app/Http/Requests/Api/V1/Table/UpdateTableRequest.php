<?php

namespace App\Http\Requests\Api\V1\Table;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'branch_id' => ['sometimes', 'nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'seats' => ['sometimes', 'required', 'integer', 'min:1'],
            'status' => ['sometimes', 'required', Rule::in(['available', 'occupied', 'reserved'])],
            'x' => ['sometimes', 'nullable', 'numeric'],
            'y' => ['sometimes', 'nullable', 'numeric'],
        ];
    }
}
