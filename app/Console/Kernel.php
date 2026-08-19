<?php

namespace App\Console;

use App\Jobs\PruneAuditLogsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel;

class Kernel extends Kernel
{
    protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new PruneAuditLogsJob)
            ->dailyAt('02:00');
    }

    protected function commands(): void
    {
        require base_path('app/Console/Commands.php');
    }
}
