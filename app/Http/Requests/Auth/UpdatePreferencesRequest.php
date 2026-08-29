<?php

namespace App\Http\Requests\Auth;

use App\Support\Theme\ThemeCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    /**
     * Always allowed: the controller only ever writes to `$request->user()`, so there is no
     * other user's record to authorise against — being authenticated *is* the authorisation.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `array:mode,palette` rejects unknown keys outright, so the stored JSON can never
     * accumulate junk the frontend will not read back.
     */
    public function rules(): array
    {
        return [
            'theme' => ['required', 'array:mode'],
            'theme.mode' => ['required', 'string', Rule::in(ThemeCatalog::MODES)],
        ];
    }
}
