<?php

namespace App\Http\Requests\Sales;

use App\Rules\VariantMatchesProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'branch_id' => ['required', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('tenant_id', $tenantId)],
            'status' => ['nullable', 'in:draft,quotation,held,completed'],
            'note' => ['nullable', 'string', 'max:1000'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'shipping_amount' => ['nullable', 'numeric', 'min:0'],
            'coupon_code' => ['nullable', 'string', 'max:64'],
            'approval_token' => ['nullable', 'string', 'max:64'],
            'tender_currency' => ['nullable', 'string', 'size:3'],
            'tender_amount' => ['nullable', 'numeric', 'min:0'],
            'fx_rate' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'items.*.variant_id' => ['nullable', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId), new VariantMatchesProduct],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['nullable', 'array'],
            'payments.*.method' => ['required_with:payments', 'in:cash,card,bank,mobile_banking,wallet,due'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
