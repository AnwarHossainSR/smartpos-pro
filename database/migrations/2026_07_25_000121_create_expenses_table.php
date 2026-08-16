<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->nullable();
            $table->timestamps();
            $table->index(['tenant_id']);
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('expense_category_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('user_id', 12)->constrained()->cascadeOnDelete();
            $table->string('reference')->nullable();
            $table->decimal('amount', 14, 2);
            $table->string('payment_method')->default('cash');
            $table->string('status')->default('approved'); // pending, approved, rejected
            $table->text('note')->nullable();
            $table->date('spent_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'spent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
    }
};
