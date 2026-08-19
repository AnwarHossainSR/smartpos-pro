<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateApprovalSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'max_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'require_approval_for_price_override' => ['required', 'boolean'],
            'require_approval_for_void' => ['required', 'boolean'],
            'approval_ttl_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
        ];
    }
}
