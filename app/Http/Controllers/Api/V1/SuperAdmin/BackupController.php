<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BackupLogResource;
use App\Jobs\CreateBackupJob;
use App\Models\BackupLog;
use App\Services\Backup\BackupService;
use App\Support\Scopes\TenantScope;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class BackupController extends Controller
{
    use ApiResponse;

    public function __construct(
        private BackupService $backupService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $logs = BackupLog::with(['creator' => fn ($q) => $q->withoutGlobalScope(TenantScope::class)->select('id', 'name', 'email')])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return $this->success(BackupLogResource::collection($logs));
    }

    public function store(Request $request): JsonResponse
    {
        $log = BackupLog::create([
            'filename' => 'backup-'.now()->format('Y-m-d-His').'-'.Str::random(8).'.sql.gz',
            'disk' => 'r2',
            'path' => '',
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        CreateBackupJob::dispatch($log);

        return $this->success(
            new BackupLogResource($log->fresh()),
            'Backup queued',
            202,
        );
    }

    public function download(BackupLog $backup): JsonResponse
    {
        if ($backup->status !== 'completed') {
            return $this->error('Backup is not ready', 422);
        }

        $url = $this->backupService->getBackupUrl($backup);

        return $this->success([
            'url' => $url,
            'filename' => $backup->filename,
            'expires_at' => now()->addHours(1)->toISOString(),
        ]);
    }

    public function destroy(BackupLog $backup): JsonResponse
    {
        $this->backupService->delete($backup);

        return $this->success(['message' => 'Backup deleted']);
    }
}
