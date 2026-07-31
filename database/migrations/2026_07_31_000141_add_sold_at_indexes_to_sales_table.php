<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every sales list, dashboard KPI and report filters on `sold_at`, but the only
 * date index on the table covered `created_at` — so the hottest queries in the
 * product were falling back to a sequential scan over the tenant's whole sales
 * history. Two indexes because there are two distinct access patterns:
 *
 *   (tenant_id, sold_at)          — the sales list filtered by date alone
 *   (tenant_id, status, sold_at)  — every report, which pins status = 'completed'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'sold_at'], 'sales_tenant_sold_at_index');
            $table->index(['tenant_id', 'status', 'sold_at'], 'sales_tenant_status_sold_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_sold_at_index');
            $table->dropIndex('sales_tenant_status_sold_at_index');
        });
    }
};
