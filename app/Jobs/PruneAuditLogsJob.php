<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Business;
use App\Models\Expense;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Purchase;
use App\Models\Sale;
use App\Support\Tenancy\TenantContext;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Str;
use App\Support\Telemetry\Telemetry;

class PruneAuditLogsJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(): int
    {
        $retentionDays = 180;

        if (app(TenantContext::class)->has()) {
            $tenantId = app(TenantContext::class)->id();
            $business = Business::withoutTenancy()
                ->where('tenant_id', $tenantId)
                ->first();

            if ($business && $business->audit_retention_days) {
                $retentionDays = $business->audit_retention_days;
            }
        }

        $cutoff = now()->subDays($retentionDays);

        $financialModels = [
            Sale::class,
            Purchase::class,
            Expense::class,
            JournalEntry::class,
            JournalLine::class,
        ];

        $logs = AuditLog::withoutTenancy()
            ->where('created_at', '<', $cutoff)
            ->whereNotIn('auditable_type', $financialModels)
            ->get();

        $count = 0;

        foreach ($logs as $log) {
            foreach (['old_values', 'new_values'] as $field) {
                if (isset($log->$field) && is_array($log->$field)) {
                    foreach ($log->$field as $key => $value) {
                        if (in_array($key, ['name', 'email', 'phone'], true) && is_string($value)) {
                            $log->$field[$key] = Str::password(12);
                        }
                    }
                }
            }

            $log->forceDelete();
            $count++;
        }

        return $count;
    }

    public function failed(\Throwable $exception): void
    {
        app(Telemetry::class)->captureException($exception);
    }
}
