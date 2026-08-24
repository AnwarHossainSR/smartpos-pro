<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    use ApiResponse;

    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'queue' => $this->checkQueue(),
            'storage' => $this->checkStorage(),
        ];

        $healthy = ! in_array(false, $checks, true);

        $release = env('APP_VERSION') ?? 'unknown';

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
            'version' => $release,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkRedis(): bool
    {
        try {
            Redis::ping();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkQueue(): bool
    {
        try {
            $queue = config('queue.default');
            $connection = config("queue.connections.{$queue}.connection");

            if ($connection === 'sync') {
                return true;
            }

            return DB::table('jobs')->limit(1)->exists() || true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function checkStorage(): bool
    {
        try {
            Storage::disk('local')->exists('health-check');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
