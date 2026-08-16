<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained('tenants');
            $table->string('reference_number')->unique();
            $table->foreignUlid('from_warehouse_id', 12)->constrained('warehouses');
            $table->foreignUlid('to_warehouse_id', 12)->constrained('warehouses');
            $table->foreignUlid('requested_by', 12)->constrained('users');
            $table->foreignUlid('approved_by', 12)->nullable()->constrained('users');
            $table->foreignUlid('received_by', 12)->nullable()->constrained('users');
            $table->enum('status', ['pending', 'approved', 'in_transit', 'received', 'cancelled'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('stock_transfer_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('product_id', 12)->constrained('products');
            $table->foreignUlid('variant_id', 12)->nullable()->constrained('product_variants');
            $table->decimal('quantity_requested', 10, 4);
            $table->decimal('quantity_received', 10, 4)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
        Schema::dropIfExists('stock_transfers');
    }
};
