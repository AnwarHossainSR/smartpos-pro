<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;

class ProductVariantObserver
{
    public function created(ProductVariant $variant): void
    {
        $this->flushProductVariantBootstrapCache($variant);
    }

    public function updated(ProductVariant $variant): void
    {
        $this->flushProductVariantBootstrapCache($variant);
    }

    public function deleted(ProductVariant $variant): void
    {
        $this->flushProductVariantBootstrapCache($variant);
    }

    private function flushProductVariantBootstrapCache(ProductVariant $variant): void
    {
        $tenantId = $variant->tenant_id;

        $branches = Branch::where('tenant_id', $tenantId)->get();

        foreach ($branches as $branch) {
            Cache::forget("pos:bootstrap:{$tenantId}:{$branch->id}");
            Cache::forget("pos:bootstrap:catalog:{$tenantId}:{$branch->id}");
        }

        Cache::forget("tax:profiles:{$tenantId}");
    }
}
