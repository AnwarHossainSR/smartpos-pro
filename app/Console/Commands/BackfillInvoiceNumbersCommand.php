<?php

namespace App\Console\Commands;

use App\Domain\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Sale;
use App\Models\Tenant;
use App\Services\Sales\InvoiceNumberService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gives an invoice number to every completed sale that predates per-tenant
 * gapless numbering, and leaves the tenant's counter parked above everything
 * already on record so the next live checkout continues the run.
 *
 * Sales that already carry a number keep it — the command only fills blanks —
 * and it is safe to run repeatedly: a second run finds nothing to do.
 */
class BackfillInvoiceNumbersCommand extends Command
{
    protected $signature = 'sales:backfill-invoice-numbers
                            {--tenant= : Backfill only this tenant id}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Assign invoice numbers to completed sales that do not have one yet.';

    public function handle(InvoiceNumberService $numbers): int
    {
        $query = Tenant::query()->withTrashed()->orderBy('id');

        if ($tenantId = $this->option('tenant')) {
            $query->whereKey($tenantId);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->components->warn('No tenants matched.');

            return self::SUCCESS;
        }

        $filled = 0;

        foreach ($tenants as $tenant) {
            $filled += $this->backfillTenant($tenant, $numbers);
        }

        $this->components->info($this->option('dry-run')
            ? "{$filled} sale(s) would be numbered across {$tenants->count()} tenant(s)."
            : "{$filled} sale(s) numbered across {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }

    private function backfillTenant(Tenant $tenant, InvoiceNumberService $numbers): int
    {
        $business = Business::withoutTenancy()
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at')
            ->first();

        $prefix = $numbers->prefixFor($business);

        // Ordered by id so the run is deterministic and reproducible, and floored
        // above any number already in use so a backfill can never mint a duplicate.
        $sales = Sale::withoutTenancy()
            ->withTrashed()
            ->where('tenant_id', $tenant->id)
            ->whereNull('invoice_number')
            ->whereNotIn('status', [
                SaleStatus::Held->value,
                SaleStatus::Draft->value,
                SaleStatus::Quotation->value,
            ])
            ->orderBy('id')
            ->get();

        if ($sales->isEmpty()) {
            return 0;
        }

        if ($this->option('dry-run')) {
            $this->components->twoColumnDetail($tenant->name, "{$sales->count()} unnumbered");

            return $sales->count();
        }

        DB::transaction(function () use ($tenant, $sales, $numbers, $prefix) {
            /** @var Tenant $locked */
            $locked = Tenant::withTrashed()->whereKey($tenant->id)->lockForUpdate()->firstOrFail();

            $sequence = max(
                (int) $locked->invoice_sequence,
                $numbers->highestIssued($tenant->id, $prefix),
            );

            foreach ($sales as $sale) {
                $sequence++;
                $sale->invoice_number = $numbers->format($sequence, $prefix);
                $sale->saveQuietly();
            }

            Tenant::whereKey($tenant->id)->update(['invoice_sequence' => $sequence]);
        });

        $this->components->twoColumnDetail($tenant->name, "{$sales->count()} numbered");

        return $sales->count();
    }
}
