<?php

namespace App\Http\Requests\Sales;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'refund_method' => ['nullable', 'in:cash,card,bank,store_credit'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
