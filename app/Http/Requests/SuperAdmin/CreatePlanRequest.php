<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class CreatePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:plans,slug,'.$this->route('id'),
            'price' => 'required|numeric|min:0',
            'interval' => 'nullable|string|in:month,year',
            'limits' => 'nullable|array',
            'features' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ];
    }
}
