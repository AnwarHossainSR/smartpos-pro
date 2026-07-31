<?php

namespace App\Http\Requests\Inventory;

use App\Rules\VariantMatchesProduct;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockSerialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'product_id' => ['required', Rule::exists('products', 'id')->where('tenant_id', $tenantId)],
            'variant_id' => ['nullable', Rule::exists('product_variants', 'id')->where('tenant_id', $tenantId), new VariantMatchesProduct],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'serial_number' => ['required', 'string', 'max:200'],
        ];
    }
}
