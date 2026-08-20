<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->uuid('id');
            $table->foreignId('tenant_id')->index();
            $table->foreignId('supplier_id')->index();
            $table->decimal('amount', 10, 2);
            $table->enum('method', ['cash', 'card', 'mobile_banking', 'bank', 'wallet', 'due']);
            $table->string('reference')->nullable()->default(null);
            $table->date('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
    }
};
