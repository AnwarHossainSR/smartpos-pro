<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('branch_id')->constrained('branches');
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('converted_to_sale_id')->nullable()->constrained('sales');
            $table->string('reference_number')->unique();
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired', 'converted'])->default('draft');
            $table->decimal('subtotal', 14, 4)->default(0);
            $table->decimal('discount_amount', 14, 4)->default(0);
            $table->decimal('tax_amount', 14, 4)->default(0);
            $table->decimal('total', 14, 4)->default(0);
            $table->text('note')->nullable();
            $table->text('terms')->nullable();
            $table->date('valid_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->decimal('quantity', 10, 4);
            $table->decimal('unit_price', 12, 4);
            $table->decimal('discount', 12, 4)->default(0);
            $table->decimal('tax_rate', 6, 4)->default(0);
            $table->decimal('subtotal', 14, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
