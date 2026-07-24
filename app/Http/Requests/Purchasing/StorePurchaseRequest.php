<?php

namespace App\Http\Requests\Purchasing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'supplier_id' => ['nullable', Rule::exists('suppliers', 'id')->where('tenant_id', $tenantId)],
            'status' => ['nullable', 'in:draft,ordered,received'],
            'note' => ['nullable', 'string', 'max:1000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.variant_id' => ['nullable', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'in:cash,card,bank,mobile_banking,wallet'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
