<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('sale_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id', 12)->nullable()->constrained('product_variants')->nullOnDelete();
            $table->string('name'); // snapshot of product name at time of sale
            $table->decimal('quantity', 16, 4);
            $table->decimal('unit_price', 14, 2);
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->index(['tenant_id', 'sale_id']);
            $table->index(['tenant_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
