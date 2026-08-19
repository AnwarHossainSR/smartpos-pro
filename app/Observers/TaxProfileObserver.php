<?php

namespace App\Observers;

use App\Models\TaxProfile;
use Illuminate\Support\Facades\Cache;

class TaxProfileObserver
{
    public function created(TaxProfile $taxProfile): void
    {
        $this->flushCache();
    }

    public function updated(TaxProfile $taxProfile): void
    {
        $this->flushCache();
    }

    public function deleted(TaxProfile $taxProfile): void
    {
        $this->flushCache();
    }

    private function flushCache(): void
    {
        Cache::forget('tax:profiles:*');
        Cache::forget('pos:bootstrap:catalog:*');
    }
}
