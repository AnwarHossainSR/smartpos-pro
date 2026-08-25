<?php

namespace App\Services\Backup;

use App\Models\BackupLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupService
{
    public function create(User $user): BackupLog
    {
        $log = BackupLog::create([
            'filename' => $this->generateFilename(),
            'disk' => 'r2',
            'path' => '',
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        try {
            $dumpPath = $this->dumpDatabase($log->filename);
            $log->path = $this->uploadToR2($dumpPath, $log->filename);
            $log->size_bytes = filesize($dumpPath);
            $log->status = 'completed';
            $log->completed_at = now();
            $log->save();

            @unlink($dumpPath);
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error = $e->getMessage();
            $log->save();

            Log::error('Backup failed', [
                'backup_log_id' => $log->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $log->fresh();
    }

    private function generateFilename(): string
    {
        return 'backup-'.now()->format('Y-m-d-His').'-'.Str::random(8).'.sql.gz';
    }

    private function dumpDatabase(string $filename): string
    {
        $dumpPath = storage_path("app/private/{$filename}");
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        if ($config['driver'] === 'pgsql') {
            $this->dumpPostgres($config, $dumpPath);
        } else {
            $this->dumpMysql($config, $dumpPath);
        }

        return $dumpPath;
    }

    private function dumpPostgres(array $config, string $dumpPath): void
    {
        $env = [
            'PGPASSWORD' => $config['password'],
        ];

        $command = sprintf(
            'pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl 2>&1 | gzip > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($config['database']),
            escapeshellarg($dumpPath),
        );

        $result = Process::env($env)->run($command);

        if (! $result->successful()) {
            throw new \RuntimeException("pg_dump failed: {$result->errorOutput()}");
        }
    }

    private function dumpMysql(array $config, string $dumpPath): void
    {
        $command = sprintf(
            'mysqldump -h %s -P %s -u %s %s --no-tablespaces 2>&1 | gzip > %s',
            escapeshellarg($config['host']),
            escapeshellarg($config['port']),
            escapeshellarg($config['username']),
            escapeshellarg($config['database']),
            escapeshellarg($dumpPath),
        );

        $env = ['MYSQL_PWD' => $config['password']];
        $result = Process::env($env)->run($command);

        if (! $result->successful()) {
            throw new \RuntimeException("mysqldump failed: {$result->errorOutput()}");
        }
    }

    private function uploadToR2(string $localPath, string $filename): string
    {
        $path = "backups/{$filename}";
        $disk = Storage::disk('r2');

        $handle = fopen($localPath, 'rb');
        if (! $handle) {
            throw new \RuntimeException("Cannot open dump file: {$localPath}");
        }

        $disk->put($path, $handle);
        fclose($handle);

        return $path;
    }

    public function getBackupUrl(BackupLog $log): ?string
    {
        if ($log->status !== 'completed') {
            return null;
        }

        $disk = Storage::disk('r2');

        return $disk->temporaryUrl($log->path, now()->addHours(1));
    }

    public function delete(BackupLog $log): bool
    {
        if ($log->status === 'completed') {
            Storage::disk('r2')->delete($log->path);
        }

        return $log->delete();
    }

    public function dumpDatabaseForJob(string $filename): string
    {
        return $this->dumpDatabase($filename);
    }

    public function uploadToR2ForJob(string $localPath, string $filename): string
    {
        return $this->uploadToR2($localPath, $filename);
    }
}
