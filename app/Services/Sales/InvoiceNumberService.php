<?php

namespace App\Services\Sales;

use App\Models\Business;
use App\Models\Sale;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Issues per-tenant, gapless invoice numbers.
 *
 * The counter lives on the tenant row. `next()` takes a `SELECT ... FOR UPDATE`
 * on that row and holds it until the surrounding transaction commits, so two
 * concurrent checkouts inside the same tenant serialise on it: the second one
 * blocks until the first commits, then reads the already-incremented value.
 * Because the increment is part of the checkout transaction, a checkout that
 * rolls back (insufficient stock, an exhausted coupon) gives its number back —
 * which is what "gapless" actually requires. Numbering is per tenant, so two
 * tenants both start at 1 and never see each other's counter.
 *
 * A number is issued when a sale is *completed*, never when a cart is merely
 * held or drafted — a parked cart that is later abandoned must not burn a
 * number. Held carts therefore carry `invoice_number = null` until resumed.
 */
class InvoiceNumberService
{
    public const DEFAULT_PREFIX = 'INV-';

    /**
     * Reserve and return the next invoice number for a tenant.
     *
     * Must be called inside a transaction — the row lock it takes is only
     * meaningful for as long as that transaction is open.
     */
    public function next(string $tenantId, ?Business $business = null): string
    {
        /** @var Tenant $tenant */
        $tenant = Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();

        $sequence = (int) $tenant->invoice_sequence + 1;

        Tenant::whereKey($tenantId)->update(['invoice_sequence' => $sequence]);

        return $this->format($sequence, $this->prefixFor($business));
    }

    /** `{prefix}{seq}` — e.g. `INV-1`, or `S/2026-1` for a tenant that set its own prefix. */
    public function format(int $sequence, string $prefix = self::DEFAULT_PREFIX): string
    {
        return $prefix.$sequence;
    }

    /**
     * The invoice prefix this business trades under, from its free-form settings
     * blob (`settings.invoice_prefix`), defaulting to `INV-`.
     */
    public function prefixFor(?Business $business): string
    {
        $prefix = trim((string) data_get($business?->settings, 'invoice_prefix', ''));

        return $prefix !== '' ? $prefix : self::DEFAULT_PREFIX;
    }

    /**
     * The highest sequence already visible in this tenant's issued numbers under
     * the given prefix. Used by the backfill to make sure it never re-issues a
     * number an existing sale already carries, and so it can be run twice safely.
     */
    public function highestIssued(string $tenantId, string $prefix): int
    {
        $numbers = Sale::withoutTenancy()
            ->withTrashed()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('invoice_number')
            ->where('invoice_number', 'like', $prefix.'%')
            ->pluck('invoice_number');

        $highest = 0;

        foreach ($numbers as $number) {
            $suffix = substr((string) $number, strlen($prefix));

            if ($suffix !== '' && ctype_digit($suffix)) {
                $highest = max($highest, (int) $suffix);
            }
        }

        return $highest;
    }

    /**
     * Raise a tenant's counter so the next number issued clears everything already
     * on record. Never lowers it — a counter is only ever allowed to move forward.
     */
    public function raiseSequenceTo(string $tenantId, int $sequence): void
    {
        DB::transaction(function () use ($tenantId, $sequence) {
            /** @var Tenant $tenant */
            $tenant = Tenant::whereKey($tenantId)->lockForUpdate()->firstOrFail();

            if ($sequence > (int) $tenant->invoice_sequence) {
                Tenant::whereKey($tenantId)->update(['invoice_sequence' => $sequence]);
            }
        });
    }
}
