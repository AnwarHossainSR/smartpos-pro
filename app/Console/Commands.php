<?php

use App\Console\Commands\BackfillInvoiceNumbersCommand;
use App\Console\Commands\PruneAuditLogsCommand;

Artisan::registerCommands([
    PruneAuditLogsCommand::class,
    BackfillInvoiceNumbersCommand::class,
]);
