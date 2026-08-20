<?php

namespace App\Http\Requests\Api\V1\Table;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'area' => 'sometimes|nullable|string|max:255',
            'seats' => 'sometimes|required|integer|min:1',
            'status' => 'sometimes|required|in:available,occupied,reserved',
            'x' => 'sometimes|nullable|numeric',
            'y' => 'sometimes|nullable|numeric',
        ];
    }
}