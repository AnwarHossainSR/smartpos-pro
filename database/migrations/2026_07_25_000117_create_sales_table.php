<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // cashier
            $table->string('invoice_number');
            $table->string('status')->default('completed'); // draft, quotation, held, completed, cancelled
            $table->string('payment_status')->default('paid'); // paid, partial, due
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('shipping_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->decimal('change_amount', 14, 2)->default(0);
            $table->decimal('due_amount', 14, 2)->default(0);
            $table->string('note')->nullable();
            $table->timestamp('sold_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id']);
            $table->index(['tenant_id', 'customer_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->unique(['tenant_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
