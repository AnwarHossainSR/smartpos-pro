<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ForecastRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => ['nullable', 'uuid'],
            'product_ids' => ['nullable', 'string'],
        ];
    }

    /**
     * The explicitly requested product ids, or an empty list when the caller wants
     * the default set. `product_ids` is a comma-separated list.
     *
     * @return array<int, string>
     */
    public function productIds(): array
    {
        if ($this->filled('product_id')) {
            return [(string) $this->input('product_id')];
        }

        if (! $this->filled('product_ids')) {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode(',', (string) $this->input('product_ids'))),
            static fn (string $id): bool => $id !== '',
        ));
    }
}
