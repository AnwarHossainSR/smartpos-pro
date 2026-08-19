<?php

namespace App\Console\Commands;

use App\Jobs\PruneAuditLogsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune';

    protected $description = 'Prune old audit logs based on retention days setting';

    public function handle(): int
    {
        $dispatched = Bus::dispatch(
            new PruneAuditLogsJob
        )->delay(now()->endOfDay());

        $this->info("Prune audit logs job dispatched. Job ID: {$dispatched->id}");

        return self::SUCCESS;
    }
}
