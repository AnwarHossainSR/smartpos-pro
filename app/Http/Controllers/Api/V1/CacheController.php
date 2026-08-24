<?php

namespace App\Http\Controllers\Api\V1;

use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CacheController
{
    use ApiResponse;

    public function flush(): JsonResponse
    {
        Cache::forget('pos:bootstrap:catalog:*');
        Cache::forget('tax:profiles:*');

        return $this->success(['status' => 'cache flushed']);
    }
}
