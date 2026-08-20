<?php

namespace App\Services\Currency;

use App\Models\Account;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\Accounting\JournalService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    public function __construct(
        protected JournalService $journal,
        protected TenantContext $tenantContext,
    ) {}

    public function getRate(string $from, string $to): float
    {
        if ($from === $to) {
            return 1.0;
        }

        $tenantId = $this->tenantContext->id();
        $cacheKey = "fx_rate:{$tenantId}:{$from}:{$to}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($tenantId, $from, $to) {
            $tenant = Tenant::find($tenantId);

            if ($tenant && $from === $tenant->base_currency) {
                return (float) $tenant->fallback_exchange_rate;
            }

            if ($tenant && $to === $tenant->base_currency) {
                $rate = (float) $tenant->fallback_exchange_rate;

                return $rate > 0 ? round(1.0 / $rate, 6) : 1.0;
            }

            return 1.0;
        });
    }

    public function convert(float $amount, string $from, string $to): float
    {
        if ($from === $to) {
            return $amount;
        }

        $rate = $this->getRate($from, $to);

        return round($amount * $rate, 2);
    }

    public function postFxGainLoss(Sale $sale, string $tenderCurrency, float $tenderAmount, float $fxRate): void
    {
        $sale->loadMissing('business');
        $business = $sale->business;
        if (! $business || ! $business->auto_post_journals) {
            return;
        }

        $tenant = Tenant::find($sale->tenant_id);
        $baseCurrency = $tenant?->base_currency ?? 'USD';

        if ($tenderCurrency === $baseCurrency) {
            return;
        }

        $baseAmount = round($tenderAmount * $fxRate, 2);
        $expectedBaseAmount = round((float) $sale->total, 2);
        $fxDifference = round($baseAmount - $expectedBaseAmount, 2);

        if (abs($fxDifference) < 0.01) {
            return;
        }

        $accountName = $fxDifference > 0 ? 'FX Gain' : 'FX Loss';
        $fxAccountId = $this->resolveAccount($accountName, $sale->tenant_id);
        $cashAccountId = $this->resolveAccount('Cash', $sale->tenant_id);

        if (! $fxAccountId || ! $cashAccountId) {
            return;
        }

        $absDifference = abs($fxDifference);

        if ($fxDifference > 0) {
            $lines = [
                ['account_id' => $cashAccountId, 'type' => 'debit', 'amount' => $absDifference],
                ['account_id' => $fxAccountId, 'type' => 'credit', 'amount' => $absDifference],
            ];
        } else {
            $lines = [
                ['account_id' => $fxAccountId, 'type' => 'debit', 'amount' => $absDifference],
                ['account_id' => $cashAccountId, 'type' => 'credit', 'amount' => $absDifference],
            ];
        }

        $this->journal->post([
            'user_id' => $sale->user_id,
            'reference' => 'FX-'.$sale->invoice_number,
            'description' => "FX gain/loss on Sale #{$sale->invoice_number} ({$tenderCurrency} → {$baseCurrency})",
            'source_type' => Sale::class,
            'source_id' => $sale->id,
            'lines' => $lines,
        ]);
    }

    private function resolveAccount(string $name, string $tenantId): ?string
    {
        $account = Account::where('tenant_id', $tenantId)
            ->where('name', $name)
            ->first();

        return $account?->id;
    }
}
