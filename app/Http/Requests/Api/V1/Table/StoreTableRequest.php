<?php

namespace App\Http\Requests\Api\V1\Table;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTableRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'seats' => ['required', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['available', 'occupied', 'reserved'])],
            'x' => ['nullable', 'numeric'],
            'y' => ['nullable', 'numeric'],
        ];
    }
}
