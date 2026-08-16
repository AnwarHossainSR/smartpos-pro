<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('warehouse_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('customer_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('user_id', 12)->constrained()->cascadeOnDelete();
            $table->string('reference_number');
            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0); // refunded amount
            $table->string('refund_method')->default('cash'); // cash, card, bank, store_credit
            $table->string('note')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
            $table->unique(['tenant_id', 'reference_number']);
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_return_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_item_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('product_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id', 12)->nullable()->constrained('product_variants')->nullOnDelete();
            $table->decimal('quantity', 16, 4);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'sale_return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
    }
};
