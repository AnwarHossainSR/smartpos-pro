<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Enums\SubscriptionStatus;
use App\Domain\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminController extends Controller
{
    use ApiResponse;

    public function __construct() {}

    public function health(): JsonResponse
    {
        $dbSize = 0;
        try {
            $result = \DB::select('SELECT pg_database_size(current_database()) as size');
            $dbSize = $result[0]->size ?? 0;
        } catch (\Throwable) {
            try {
                $path = \DB::getDatabasePath();
                $dbSize = file_exists($path) ? filesize($path) : 0;
            } catch (\Throwable) {
            }
        }

        $pendingJobs = 0;
        $failedJobs = 0;
        try {
            $pendingJobs = \DB::table('jobs')->count();
            $failedJobs = \DB::table('failed_jobs')->count();
        } catch (\Throwable) {
        }

        $cacheDriver = config('cache.default');
        $cacheWorking = true;
        try {
            cache()->put('__health_check', true);
            $cacheWorking = cache()->get('__health_check') === true;
        } catch (\Throwable) {
            $cacheWorking = false;
        }

        $diskTotal = @disk_total_space('/') ?: 0;
        $diskFree = @disk_free_space('/') ?: 0;

        $r2Metrics = [
            'configured' => false,
            'connected' => false,
            'bucket' => null,
            'object_count' => null,
            'total_size_bytes' => null,
            'total_size_human' => null,
        ];

        try {
            $r2Disk = \Storage::disk('r2');
            $r2Metrics['configured'] = true;
            $r2Metrics['bucket'] = config('filesystems.disks.r2.bucket');

            $objects = $r2Disk->allFiles('/');
            $r2Metrics['connected'] = true;
            $r2Metrics['object_count'] = count($objects);

            $totalSize = 0;
            foreach ($objects as $object) {
                $totalSize += $r2Disk->size($object);
            }
            $r2Metrics['total_size_bytes'] = $totalSize;
            $r2Metrics['total_size_human'] = $this->humanFileSize($totalSize);
        } catch (\Throwable) {
            $r2Metrics['connected'] = false;
        }

        return $this->success([
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => [
                'connected' => \DB::getPdo() !== null,
                'size_mb' => round($dbSize / 1024 / 1024, 2),
            ],
            'queue' => [
                'connection' => config('queue.default'),
                'pending' => $pendingJobs,
                'failed' => $failedJobs,
            ],
            'cache' => [
                'driver' => $cacheDriver,
                'working' => $cacheWorking,
            ],
            'storage' => [
                'total_mb' => round($diskTotal / 1024 / 1024, 0),
                'free_mb' => round($diskFree / 1024 / 1024, 0),
            ],
            'r2' => $r2Metrics,
        ]);
    }

    public function revenueMetrics(Request $request): JsonResponse
    {
        $period = $request->integer('days', 30);
        $from = now()->subDays($period);

        $planDistribution = Subscription::withoutTenancy()->where('status', SubscriptionStatus::Active)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw('plans.name as plan_name, plans.slug, count(*) as count, sum(plans.price) as mrr')
            ->groupBy('plans.id', 'plans.name', 'plans.slug')
            ->get();

        $newTenants = Tenant::where('created_at', '>=', $from)->count();
        $activeTenants = Tenant::where('status', TenantStatus::Active)->count();
        $suspendedTenants = Tenant::where('status', TenantStatus::Suspended)->count();

        $totalMrr = $planDistribution->sum('mrr');

        $recentSubscriptions = Subscription::withoutTenancy()->with(['plan'])
            ->where('created_at', '>=', $from)
            ->latest()
            ->limit(20)
            ->get();

        return $this->success([
            'total_mrr' => $totalMrr,
            'active_tenants' => $activeTenants,
            'suspended_tenants' => $suspendedTenants,
            'new_tenants_last_days' => $newTenants,
            'plan_distribution' => $planDistribution,
            'recent_subscriptions' => $recentSubscriptions,
        ]);
    }

    private function humanFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
