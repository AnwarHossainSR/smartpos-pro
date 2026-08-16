<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_registers', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id', 12)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id']);
        });

        Schema::create('cash_register_sessions', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('cash_register_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id', 12)->constrained()->cascadeOnDelete();
            $table->decimal('opening_cash', 16, 2)->default(0);
            $table->decimal('cash_in', 16, 2)->default(0);
            $table->decimal('cash_out', 16, 2)->default(0);
            $table->decimal('expected_cash', 16, 2)->default(0);
            $table->decimal('actual_cash', 16, 2)->nullable();
            $table->decimal('difference', 16, 2)->nullable();
            $table->string('status')->default('open'); // open, closed
            $table->string('note')->nullable();
            $table->timestamp('opened_at')->useCurrent();
            $table->timestamp('closed_at')->nullable();

            $table->index(['tenant_id', 'cash_register_id', 'status']);
        });

        Schema::create('cash_register_transactions', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('cash_register_session_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // in, out
            $table->decimal('amount', 16, 2);
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'cash_register_session_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_transactions');
        Schema::dropIfExists('cash_register_sessions');
        Schema::dropIfExists('cash_registers');
    }
};
