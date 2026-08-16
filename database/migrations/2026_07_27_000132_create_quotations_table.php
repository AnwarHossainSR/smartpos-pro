<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained('tenants');
            $table->foreignUlid('branch_id', 12)->constrained('branches');
            $table->foreignUlid('customer_id', 12)->nullable()->constrained('customers');
            $table->foreignUlid('user_id', 12)->constrained('users');
            $table->foreignUlid('converted_to_sale_id', 12)->nullable()->constrained('sales');
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
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('quotation_id', 12)->constrained('quotations')->cascadeOnDelete();
            $table->foreignUlid('product_id', 12)->constrained('products');
            $table->foreignUlid('variant_id', 12)->nullable()->constrained('product_variants');
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
