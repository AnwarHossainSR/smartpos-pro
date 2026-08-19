<?php

namespace App\Services\Cache;

use App\Models\TaxProfile;

class TaxProfileCacheService
{
    public function getTaxProfiles($tenantId): array
    {
        return cache()->remember(
            "tax:profiles:{$tenantId}",
            3600,
            function () use ($tenantId) {
                return TaxProfile::where('tenant_id', $tenantId)->get()->toArray();
            }
        );
    }
}
