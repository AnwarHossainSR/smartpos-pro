<?php

namespace App\Http\Requests\SuperAdmin;

use Illuminate\Foundation\Http\FormRequest;

class IndexAuditLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'uuid'],
            'tenant_id' => ['nullable', 'uuid'],
            'impersonated_only' => ['nullable', 'boolean'],
            // Unvalidated dates reached the query builder as raw strings before, which
            // silently matched nothing rather than telling the caller their filter was junk.
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
