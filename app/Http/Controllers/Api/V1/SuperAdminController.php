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

    /**
     * Cached briefly: the screen polls, and several of these probes hit the database,
     * the queue tables and object storage. A minute-old answer is still an accurate
     * answer for everything here.
     */
    public function health(): JsonResponse
    {
        return $this->success(cache()->remember(
            'platform:health',
            now()->addMinute(),
            fn () => $this->collectHealth(),
        ));
    }

    /** @return array<string, mixed> */
    private function collectHealth(): array
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
            cache()->put('__health_check', true, now()->addMinute());
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
        ];

        try {
            $r2Disk = \Storage::disk('r2');
            $r2Metrics['configured'] = true;
            $r2Metrics['bucket'] = config('filesystems.disks.r2.bucket');

            // Connectivity only. Totalling the bucket meant listing every object and then
            // one `size()` API call *per object* — a billable, multi-second request that
            // grows without bound with the tenant's media library, on a health check.
            $r2Disk->directories('/');
            $r2Metrics['connected'] = true;
        } catch (\Throwable) {
            $r2Metrics['connected'] = false;
        }

        return [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'database' => [
                'connected' => \DB::getPdo() !== null,
                // `size` (human) is what the health screen renders; `size_mb` stays for
                // anything reading the raw number. It only ever had the latter, so the
                // screen showed a dash where the database size should be.
                'size' => $this->humanFileSize((int) $dbSize),
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
                'total' => $this->humanFileSize((int) $diskTotal),
                'free' => $this->humanFileSize((int) $diskFree),
                'total_mb' => round($diskTotal / 1024 / 1024, 0),
                'free_mb' => round($diskFree / 1024 / 1024, 0),
            ],
            'r2' => $r2Metrics,
        ];
    }

    public function revenueMetrics(Request $request): JsonResponse
    {
        // Unbounded before: a negative value pushed `from` into the future and returned
        // empty metrics with no error, a huge one scanned the whole table.
        $period = (int) $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ])['days'] ?? 30;

        $from = now()->subDays($period);

        $planDistribution = Subscription::withoutTenancy()->where('status', SubscriptionStatus::Active)
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->selectRaw(
                // MRR means *monthly* recurring revenue, so an annual plan contributes a
                // twelfth of its price. Summing `plans.price` flat counted a $1,200/year
                // plan as $1,200/month.
                'plans.name as plan_name, plans.slug, count(*) as count, '.
                "sum(case when plans.interval = 'year' then plans.price / 12 else plans.price end) as mrr",
            )
            ->groupBy('plans.id', 'plans.name', 'plans.slug')
            ->get();

        $newTenants = Tenant::where('created_at', '>=', $from)->count();
        $activeTenants = Tenant::where('status', TenantStatus::Active)->count();
        $suspendedTenants = Tenant::where('status', TenantStatus::Suspended)->count();

        $totalMrr = round((float) $planDistribution->sum('mrr'), 2);

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
