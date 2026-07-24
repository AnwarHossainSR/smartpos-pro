<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['nullable', 'in:retail,supermarket,pharmacy,electronics,fashion,restaurant,wholesale'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
