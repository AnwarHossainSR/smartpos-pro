<?php

namespace App\Http\Requests\Hr;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Same rule as the listing: without `users.view` the report is silently narrowed
 * to the caller's own hours (see ShiftController::report), and asking for someone
 * else's explicitly is a 403 rather than an empty report.
 */
class ShiftReportRequest extends FormRequest
{
    /** Longest reportable window, mirroring ReportController::MAX_RANGE_DAYS. */
    public const MAX_RANGE_DAYS = 366;

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
            'group_by' => ['nullable', 'in:day,week'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }

    public function canSeeEveryone(): bool
    {
        return (bool) $this->user()?->hasPermission('users.view');
    }
}
