<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_id', 12)->constrained()->cascadeOnDelete();
            $table->string('method'); // cash, card, bank, mobile_banking, wallet, due
            $table->decimal('amount', 14, 2);
            $table->string('reference')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
