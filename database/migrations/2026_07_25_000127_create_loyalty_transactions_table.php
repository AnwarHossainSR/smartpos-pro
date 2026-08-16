<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Append-only ledger of loyalty point movements per customer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('user_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // earn, redeem, adjust
            $table->integer('points'); // signed: positive earn, negative redeem
            $table->integer('balance_after');
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'customer_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
    }
};
