<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ReceiveStockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => ['sometimes', 'array'],
            'items.*.transfer_item_id' => ['required', 'string'],
            'items.*.quantity_received' => ['required', 'numeric', 'min:0'],
        ];
    }
}
