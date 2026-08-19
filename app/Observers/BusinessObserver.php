<?php

namespace App\Observers;

use App\Models\Business;
use Illuminate\Support\Facades\Cache;

class BusinessObserver
{
    public function created(Business $business): void
    {
        $this->flushBusinessBootstrapCache($business);
    }

    public function updated(Business $business): void
    {
        $this->flushBusinessBootstrapCache($business);
    }

    public function deleted(Business $business): void
    {
        $this->flushBusinessBootstrapCache($business);
    }

    private function flushBusinessBootstrapCache(Business $business): void
    {
        $tenantId = $business->tenant_id;
        $branches = $business->branches()->get();

        foreach ($branches as $branch) {
            Cache::forget("pos:bootstrap:{$tenantId}:{$branch->id}");
            Cache::forget("pos:bootstrap:catalog:{$tenantId}:{$branch->id}");
        }

        Cache::forget("tax:profiles:{$tenantId}");
    }
}
