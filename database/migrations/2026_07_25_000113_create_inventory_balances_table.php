<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_balances', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('variant_id', 12)->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignUlid('warehouse_id', 12)->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 16, 4)->default(0);
            $table->decimal('reserved_quantity', 16, 4)->default(0);
            $table->decimal('average_cost', 14, 4)->default(0);
            $table->timestamps();

            // One balance row per product/variant/warehouse — target of atomic upserts + row locks.
            $table->unique(['tenant_id', 'product_id', 'variant_id', 'warehouse_id'], 'inventory_balance_unique');
            $table->index(['tenant_id', 'warehouse_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_balances');
    }
};
