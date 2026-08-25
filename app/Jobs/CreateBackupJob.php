<?php

namespace App\Jobs;

use App\Models\BackupLog;
use App\Services\Backup\BackupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public BackupLog $backupLog,
    ) {}

    public function handle(BackupService $backupService): void
    {
        try {
            $dumpPath = $backupService->dumpDatabaseForJob($this->backupLog->filename);
            $this->backupLog->path = $backupService->uploadToR2ForJob($dumpPath, $this->backupLog->filename);
            $this->backupLog->size_bytes = filesize($dumpPath);
            $this->backupLog->status = 'completed';
            $this->backupLog->completed_at = now();
            $this->backupLog->save();

            @unlink($dumpPath);
        } catch (\Throwable $e) {
            $this->backupLog->status = 'failed';
            $this->backupLog->error = $e->getMessage();
            $this->backupLog->save();

            Log::error('Backup failed', [
                'backup_log_id' => $this->backupLog->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->backupLog->status = 'failed';
        $this->backupLog->error = $exception->getMessage();
        $this->backupLog->save();

        Log::error('Backup job failed', [
            'backup_log_id' => $this->backupLog->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
