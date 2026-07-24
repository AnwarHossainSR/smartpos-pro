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
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference');
            $table->date('entry_date');
            $table->string('description')->nullable();
            $table->decimal('total_debit', 16, 2)->default(0);
            $table->decimal('total_credit', 16, 2)->default(0);
            $table->nullableMorphs('source'); // Sale, Purchase, Expense, ...
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'entry_date']);
            $table->unique(['tenant_id', 'reference']);
        });

        Schema::create('journal_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('journal_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // debit, credit
            $table->decimal('amount', 16, 2);
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
