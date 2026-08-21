<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // cash, bank, mobile_banking, wallet, income, expense, asset, liability, equity
            $table->string('normal_side')->nullable(); // debit or credit
            $table->string('account_number')->nullable();
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('balance', 16, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id']);

            // Every list endpoint runs `where tenant_id = ? order by name limit n`.
            // Without this the whole tenant's rows are read and filesorted to return
            // one page.
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
