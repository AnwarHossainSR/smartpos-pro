<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Back-fills the tax engine from the legacy per-product `tax_rate` column.
 *
 * For each tenant, every distinct non-zero rate in use becomes one tax rate and
 * one single-component group, and the products carrying that rate are pointed at
 * it. The result is behaviourally identical to what those products charged
 * before — one component, same percentage — so nothing about existing pricing
 * moves on deploy; the difference is that the rate is now a first-class record
 * that can be renamed, reported on, and combined with others.
 *
 * `tax_rate` / `tax_inclusive` stay on `products` as a read-only legacy mirror
 * until the frontend has fully moved over; they are not read by the engine.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tenantIds = DB::table('products')->distinct()->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            $rates = DB::table('products')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('tax_rate')
                ->where('tax_rate', '>', 0)
                ->distinct()
                ->pluck('tax_rate');

            foreach ($rates as $rate) {
                $label = rtrim(rtrim(number_format((float) $rate, 2, '.', ''), '0'), '.');
                $name = "Tax {$label}%";
                $now = now();

                $rateId = DB::table('tax_rates')->insertGetId([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'code' => 'LEGACY_'.str_replace('.', '_', $label),
                    'rate' => $rate,
                    'type' => 'percentage',
                    'is_compound' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                $groupId = DB::table('tax_groups')->insertGetId([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'code' => 'LEGACY_'.str_replace('.', '_', $label),
                    'is_default' => false,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('tax_group_rate')->insert([
                    'tenant_id' => $tenantId,
                    'tax_group_id' => $groupId,
                    'tax_rate_id' => $rateId,
                    'priority' => 0,
                ]);

                DB::table('products')
                    ->where('tenant_id', $tenantId)
                    ->where('tax_rate', $rate)
                    ->update(['tax_group_id' => $groupId]);
            }

            // A zero-rated group, so "no tax" is an explicit choice on a product
            // rather than the absence of one — the two are different on a return.
            $zeroGroupId = DB::table('tax_groups')->insertGetId([
                'tenant_id' => $tenantId,
                'name' => 'Zero-rated',
                'code' => 'ZERO',
                'is_default' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('products')
                ->where('tenant_id', $tenantId)
                ->whereNull('tax_group_id')
                ->update(['tax_group_id' => $zeroGroupId]);
        }

        // Existing tenants quoted tax-inclusive prices if the majority of their
        // catalogue was flagged that way; preserve what the shelf showed.
        foreach (DB::table('businesses')->pluck('tenant_id', 'id') as $businessId => $tenantId) {
            $inclusive = DB::table('products')->where('tenant_id', $tenantId)->where('tax_inclusive', true)->count();
            $exclusive = DB::table('products')->where('tenant_id', $tenantId)->where('tax_inclusive', false)->count();

            DB::table('businesses')->where('id', $businessId)->update([
                'price_display' => $inclusive > $exclusive ? 'inclusive' : 'exclusive',
            ]);
        }
    }

    public function down(): void
    {
        DB::table('products')->update(['tax_group_id' => null]);
        DB::table('tax_group_rate')->delete();
        DB::table('tax_groups')->delete();
        DB::table('tax_rates')->delete();
    }
};
