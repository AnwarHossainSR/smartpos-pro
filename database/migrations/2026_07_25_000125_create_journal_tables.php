<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable double-entry accounting ledger. Every journal entry's debit lines
 * must equal its credit lines; account balances are a projection of these lines.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference');
            $table->date('entry_date');
            $table->string('description')->nullable();
            $table->decimal('total_debit', 16, 2)->default(0);
            $table->decimal('total_credit', 16, 2)->default(0);
            $table->nullableUuidMorphs('source'); // Sale, Purchase, Expense, ...
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'entry_date']);
            $table->unique(['tenant_id', 'reference']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('account_id')->constrained()->cascadeOnDelete();
            // Support both historical debit/credit columns used by seeders
            // and `type`/`amount` style used by application code.
            $table->decimal('debit', 16, 2)->default(0);
            $table->decimal('credit', 16, 2)->default(0);
            $table->string('type')->nullable(); // debit, credit
            $table->decimal('amount', 16, 2)->nullable();
            $table->string('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'account_id']);
            $table->index(['tenant_id', 'journal_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_lines');
        Schema::dropIfExists('journal_entries');
    }
};
