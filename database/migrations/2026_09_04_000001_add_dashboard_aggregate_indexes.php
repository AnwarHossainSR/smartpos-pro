<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Covering indexes for the two dashboard aggregates that had none.
 *
 * Both are unbounded sums over a tenant's whole table — they carry no date
 * range, so they grow without limit as the tenant trades and were reading every
 * row of `sales` / `customers` on each dashboard load:
 *
 *   sum(due_amount) where payment_status != 'paid'
 *   sum(balance)    where balance > 0
 *
 * The trailing summed column is what makes these worth adding: it keeps the sum
 * inside the index, so neither query touches the base table at all. The `sales`
 * one is deliberately (tenant_id, payment_status, due_amount) even though the
 * predicate is `!=` and so not selective — the win here is the covering scan,
 * not the seek.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['tenant_id', 'payment_status', 'due_amount'], 'sales_tenant_payment_due_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->index(['tenant_id', 'balance'], 'customers_tenant_balance_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_tenant_payment_due_index');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex('customers_tenant_balance_index');
        });
    }
};
