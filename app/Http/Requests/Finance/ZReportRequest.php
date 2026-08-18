<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Query validation for the end-of-day Z-report: a single trading day and an
 * optional branch. Both the JSON and the PDF endpoint share it, so the two can
 * never disagree about what "the day" means.
 */
class ZReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;

        return [
            'date' => ['nullable', 'date'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'download' => ['nullable', 'boolean'],
        ];
    }

    /** The trading day being closed — today when the caller doesn't say. */
    public function reportDate(): Carbon
    {
        return ($this->date('date') ?? Carbon::now())->startOfDay();
    }

    public function branchId(): ?string
    {
        return $this->filled('branch_id') ? (string) $this->input('branch_id') : null;
    }
}
