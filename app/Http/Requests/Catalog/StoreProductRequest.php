<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
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
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->where('tenant_id', $tenantId)],
            'unit_id' => ['nullable', Rule::exists('units', 'id')->where('tenant_id', $tenantId)],
            'sku' => ['nullable', 'string', 'max:100', Rule::unique('products', 'sku')->where('tenant_id', $tenantId)],
            'barcode' => ['nullable', 'string', 'max:100', Rule::unique('products', 'barcode')->where('tenant_id', $tenantId)],
            'type' => ['nullable', 'in:standard,variant,service,digital,combo'],
            'description' => ['nullable', 'string'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0'],
            'tax_group_id' => ['nullable', Rule::exists('tax_groups', 'id')->where('tenant_id', $tenantId)],
            'min_stock_level' => ['nullable', 'numeric', 'min:0'],
            'reorder_quantity' => ['nullable', 'numeric', 'min:0'],
            'track_inventory' => ['boolean'],
            'is_active' => ['boolean'],
            'tags' => ['nullable', 'array'],
            'opening_stock' => ['nullable', 'array'],
            'opening_stock.warehouse_id' => ['required_with:opening_stock', Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId)],
            'opening_stock.quantity' => ['required_with:opening_stock', 'numeric', 'min:0'],
        ];
    }
}
