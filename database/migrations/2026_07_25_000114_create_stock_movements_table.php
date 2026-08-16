<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Immutable stock ledger. Rows are append-only: every physical stock change
 * (sale, purchase, adjustment, transfer, return) writes one row here. Balances
 * in inventory_balances are a materialised projection of this ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignUuid('warehouse_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // sale, purchase, adjustment_in, adjustment_out, transfer_in, transfer_out, sale_return, purchase_return, opening
            $table->string('direction'); // in, out
            $table->decimal('quantity', 16, 4);        // always positive magnitude
            $table->decimal('quantity_after', 16, 4);  // running balance after this movement
            $table->decimal('unit_cost', 14, 4)->default(0);
            $table->nullableUuidMorphs('reference'); // reference_type / reference_id (Sale, Purchase, StockAdjustment...)
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'product_id', 'warehouse_id']);
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
