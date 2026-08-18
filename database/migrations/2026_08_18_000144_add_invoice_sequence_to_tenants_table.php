<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The per-tenant invoice counter. It lives on the tenant row (not on a separate
 * sequence table) so a checkout can take a single `SELECT ... FOR UPDATE` on it
 * and hold that lock for the rest of the transaction — which is what makes the
 * resulting numbering gapless under concurrent checkouts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_sequence')->default(0)->after('feature_limits');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('invoice_sequence');
        });
    }
};
