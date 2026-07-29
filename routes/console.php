<?php

use App\Jobs\SendDailyReportJob;
use App\Models\IdempotencyKey;
use App\Models\Tenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// T36 — Scheduled reports: send daily sales email to every active tenant at 07:00 UTC
Schedule::call(function () {
    Tenant::where('status', 'active')->pluck('id')->each(function (int $tenantId) {
        SendDailyReportJob::dispatch($tenantId);
    });
})->dailyAt('07:00')->name('daily-reports')->withoutOverlapping();

// Auto-expire trials: move tenants past trial end to 'suspended'
Schedule::call(function () {
    Tenant::where('status', 'trialing')
        ->where('trial_ends_at', '<', now())
        ->update(['status' => 'suspended']);
})->hourly()->name('expire-trials');

// Idempotency keys only need to survive long enough to cover client retries.
Schedule::call(function () {
    IdempotencyKey::withoutGlobalScopes()->where('created_at', '<', now()->subDay())->delete();
})->hourly()->name('prune-idempotency-keys');
