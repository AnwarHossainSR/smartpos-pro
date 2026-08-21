<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->enum('method', ['cash', 'card', 'mobile_banking', 'bank', 'wallet', 'due']);
            $table->string('reference')->nullable();
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tenant_id']);
            $table->index(['supplier_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
