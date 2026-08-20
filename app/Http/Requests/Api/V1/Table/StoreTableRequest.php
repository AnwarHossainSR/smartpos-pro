<?php

namespace App\Http\Requests\Api\V1\Table;

use Illuminate\Foundation\Http\FormRequest;

class StoreTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'area' => 'nullable|string|max:255',
            'seats' => 'required|integer|min:1',
            'status' => 'required|in:available,occupied,reserved',
            'x' => 'nullable|numeric',
            'y' => 'nullable|numeric',
        ];
    }
}