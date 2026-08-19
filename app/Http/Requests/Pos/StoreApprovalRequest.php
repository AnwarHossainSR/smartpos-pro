<?php

namespace App\Http\Requests\Pos;

use App\Domain\Enums\ApprovalAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['required', ApprovalAction::rule()],
            // Digits only: the terminal renders a numeric keypad, and a PIN that
            // isn't all digits could never have been typed on it.
            'pin' => ['required', 'string', 'regex:/^\d{4,8}$/'],
            'requested_value' => ['nullable', 'numeric', 'min:0'],
            'limit_value' => ['nullable', 'numeric', 'min:0'],
            'sale_id' => ['nullable', Rule::exists('sales', 'id')->where('tenant_id', $this->user()?->tenant_id)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return ['pin.regex' => 'The PIN must be 4 to 8 digits.'];
    }
}
