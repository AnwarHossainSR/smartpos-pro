<?php

namespace App\Observers;

use App\Models\Branch;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    public function created(Product $product): void
    {
        $this->flushProductBootstrapCache($product);
    }

    public function updated(Product $product): void
    {
        $this->flushProductBootstrapCache($product);
    }

    public function deleted(Product $product): void
    {
        $this->flushProductBootstrapCache($product);
    }

    private function flushProductBootstrapCache(Product $product): void
    {
        $tenantId = $product->tenant_id;

        $branches = Branch::where('tenant_id', $tenantId)->get();

        foreach ($branches as $branch) {
            Cache::forget("pos:bootstrap:{$tenantId}:{$branch->id}");
            Cache::forget("pos:bootstrap:catalog:{$tenantId}:{$branch->id}");
        }

        Cache::forget("tax:profiles:{$tenantId}");
    }
}
