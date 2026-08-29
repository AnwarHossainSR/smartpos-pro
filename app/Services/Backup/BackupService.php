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

    /**
     * The smallest a real gzipped dump can plausibly be. A gzip header alone is 20
     * bytes, and an empty-but-valid archive round-trips through `gzip -t` happily —
     * so the integrity check needs a floor under it to catch a dump that produced
     * nothing at all.
     */
    private const MIN_DUMP_BYTES = 1024;

    private function dumpDatabase(string $filename): string
    {
        $dumpPath = storage_path("app/private/{$filename}");

        if (! is_dir(dirname($dumpPath))) {
            mkdir(dirname($dumpPath), 0775, true);
        }

        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        try {
            if ($config['driver'] === 'pgsql') {
                $this->dumpPostgres($config, $dumpPath);
            } else {
                $this->dumpMysql($config, $dumpPath);
            }

            $this->assertDumpIsIntact($dumpPath);
        } catch (\Throwable $e) {
            // `> $dumpPath` creates the file before the dump tool runs, so a failure
            // always leaves a truncated one behind. Don't let it accumulate on disk,
            // and don't leave something restore-shaped lying next to real backups.
            @unlink($dumpPath);

            throw $e;
        }

        return $dumpPath;
    }

    private function dumpPostgres(array $config, string $dumpPath): void
    {
        $this->runDump(
            'pg_dump',
            sprintf(
                'pg_dump -h %s -p %s -U %s -d %s --no-owner --no-acl | gzip > %s',
                escapeshellarg($config['host']),
                escapeshellarg($config['port']),
                escapeshellarg($config['username']),
                escapeshellarg($config['database']),
                escapeshellarg($dumpPath),
            ),
            ['PGPASSWORD' => $config['password']],
        );
    }

    private function dumpMysql(array $config, string $dumpPath): void
    {
        $this->runDump(
            'mysqldump',
            sprintf(
                'mysqldump -h %s -P %s -u %s %s --no-tablespaces | gzip > %s',
                escapeshellarg($config['host']),
                escapeshellarg($config['port']),
                escapeshellarg($config['username']),
                escapeshellarg($config['database']),
                escapeshellarg($dumpPath),
            ),
            ['MYSQL_PWD' => $config['password']],
        );
    }

    /**
     * Runs a `dump | gzip` pipeline so that a failure in the *dump* half is actually
     * reported.
     *
     * Two things make that work, and both were missing before:
     *   - `pipefail`, because a pipeline otherwise exits with the status of its last
     *     command — gzip, which succeeds on empty input, so a dead pg_dump looked
     *     like a clean backup;
     *   - keeping stderr on stderr. Redirecting it into stdout wrote the dump tool's
     *     warnings into the archive body, corrupting it, and left `errorOutput()`
     *     empty so there was nothing to report either.
     *
     * @param  array<string, string|null>  $env
     */
    private function runDump(string $tool, string $pipeline, array $env): void
    {
        // `sh` does not have pipefail; the option is a bash/zsh extension.
        $result = Process::env($env)->run(['bash', '-o', 'pipefail', '-c', $pipeline]);

        if (! $result->successful()) {
            throw new \RuntimeException(sprintf(
                '%s failed (exit %d): %s',
                $tool,
                $result->exitCode(),
                trim($result->errorOutput()) ?: 'no error output',
            ));
        }
    }

    /** A dump nobody can restore is worse than no dump, so verify before recording success. */
    private function assertDumpIsIntact(string $dumpPath): void
    {
        if (! is_file($dumpPath)) {
            throw new \RuntimeException("Dump file was never written: {$dumpPath}");
        }

        $size = filesize($dumpPath);

        if ($size < self::MIN_DUMP_BYTES) {
            throw new \RuntimeException(
                "Dump file is implausibly small ({$size} bytes) — treating it as a failed dump.",
            );
        }

        $verify = Process::run(['gzip', '-t', $dumpPath]);

        if (! $verify->successful()) {
            throw new \RuntimeException(
                'Dump file failed its gzip integrity check: '.(trim($verify->errorOutput()) ?: 'corrupt archive'),
            );
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
