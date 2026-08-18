<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `shifts.view` (route middleware) lets a user see *their own* timesheet. Reading
 * somebody else's is a team-management action, so it additionally requires
 * `users.view` — checked here rather than on the route, since the same endpoint
 * serves both cases depending on `?user_id=`.
 */
class IndexShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $requested = $this->query('user_id');

        if ($requested === null || $requested === '' || $requested === $user?->id) {
            return true;
        }

        return (bool) $user?->hasPermission('users.view');
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'user_id' => ['nullable', 'uuid', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'status' => ['nullable', 'in:open,closed'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** True when the caller may see rows belonging to users other than themselves. */
    public function canSeeEveryone(): bool
    {
        return (bool) $this->user()?->hasPermission('users.view');
    }
}
