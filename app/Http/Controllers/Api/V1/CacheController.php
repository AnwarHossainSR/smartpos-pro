<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class CacheController
{
    public function flush(): JsonResponse
    {
        Cache::forget('pos:bootstrap:catalog:*');
        Cache::forget('tax:profiles:*');

        return response()->json(['status' => 'cache flushed']);
    }
}
