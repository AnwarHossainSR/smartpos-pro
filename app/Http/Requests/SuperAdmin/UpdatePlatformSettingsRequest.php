<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'settings' => 'required|array',
            'settings.modules' => 'sometimes|array',
            'settings.modules.*' => 'boolean',
            'settings.tenant_overrides' => 'sometimes|array',
            'settings.tenant_overrides.*' => 'array',
            'settings.maintenance_mode' => 'sometimes|boolean',
            'settings.registration_enabled' => 'sometimes|boolean',
            'settings.max_tenants' => 'sometimes|nullable|integer|min:1',
        ];
    }
}
