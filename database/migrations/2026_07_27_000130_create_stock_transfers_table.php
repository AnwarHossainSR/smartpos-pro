<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->string('reference_number')->unique();
            $table->foreignUuid('from_warehouse_id')->constrained('warehouses');
            $table->foreignUuid('to_warehouse_id')->constrained('warehouses');
            $table->foreignUuid('requested_by')->constrained('users');
            $table->foreignUuid('approved_by')->nullable()->constrained('users');
            $table->foreignUuid('received_by')->nullable()->constrained('users');
            $table->enum('status', ['pending', 'approved', 'in_transit', 'received', 'cancelled'])->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('stock_transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('stock_transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('variant_id')->nullable()->constrained('product_variants');
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
