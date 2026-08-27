<?php

use App\Jobs\SendDailyReportJob;
use App\Models\IdempotencyKey;
use App\Models\Impersonation;
use App\Models\Tenant;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// T36 — Scheduled reports: send daily sales email to every active tenant at 07:00 UTC
Schedule::call(function () {
    Tenant::where('status', 'active')->pluck('id')->each(function (string $tenantId) {
        SendDailyReportJob::dispatch($tenantId);
    });
})->dailyAt('07:00')->name('daily-reports')->withoutOverlapping();

// Idempotency keys only need to survive long enough to cover client retries.
Schedule::call(function () {
    IdempotencyKey::withoutGlobalScopes()->where('created_at', '<', now()->subDay())->delete();
})->hourly()->name('prune-idempotency-keys');

// An impersonation token expires on its own after 30 minutes, but nothing writes
// `ended_at` when it simply lapses — so close those rows out, or the platform's
// "active support sessions" view accumulates sessions that ended long ago.
Schedule::call(function () {
    Impersonation::lapsed()->update([
        'ended_at' => now(),
        'updated_at' => now(),
    ]);
})->hourly()->name('close-lapsed-impersonations');

// Sanctum tokens carry their own expires_at (config/sanctum.php); sweep the expired rows daily.
Schedule::command('sanctum:prune-expired --hours=24')->daily()->name('prune-expired-tokens');

// G30 — Nightly backups with cleanup
Schedule::command('backup:clean')->daily()->at('02:00')->name('backup-clean');
Schedule::command('backup:run')->daily()->at('03:00')->name('backup-run');
Schedule::command('backup:monitor')->daily()->at('04:00')->name('backup-monitor');
