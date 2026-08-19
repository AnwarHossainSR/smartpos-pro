<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SetApprovalPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Re-authenticating stops someone who walked up to an unlocked till from
            // silently giving themselves an approval PIN. Checked in the controller
            // against the caller's own hash, not through a guard.
            'current_password' => ['required', 'string'],
            'pin' => ['required', 'string', 'regex:/^\d{4,8}$/', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return ['pin.regex' => 'The PIN must be 4 to 8 digits.'];
    }
}
