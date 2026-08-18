<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `sales.invoice_number` already existed (with its `(tenant_id, invoice_number)`
 * unique index) but was NOT NULL, which forced the old placeholder-then-update
 * pattern. A number is now issued only when a sale is *completed*, so a held or
 * draft cart must legitimately carry no number at all — hence nullable. The
 * unique index is unchanged: NULLs do not collide in a unique index on either
 * MySQL or SQLite, so any number of carts can sit held at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('invoice_number')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('invoice_number')->nullable(false)->change();
        });
    }
};
