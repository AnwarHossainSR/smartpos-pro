<?php

namespace App\Services\SuperAdmin;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ImpersonationService
{
    private const CACHE_PREFIX = 'impersonate:';

    private const TTL_MINUTES = 60;

    public function start(User $superAdmin, string $targetUserId): string
    {
        $token = Str::random(64);
        Cache::put(
            self::CACHE_PREFIX.$token,
            ['super_admin_id' => $superAdmin->id, 'target_user_id' => $targetUserId],
            now()->addMinutes(self::TTL_MINUTES),
        );

        return $token;
    }

    public function resolve(string $token): ?array
    {
        return Cache::get(self::CACHE_PREFIX.$token);
    }

    public function stop(string $token): void
    {
        Cache::forget(self::CACHE_PREFIX.$token);
    }
}
