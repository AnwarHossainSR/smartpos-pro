<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stocktakes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained('tenants');
            $table->foreignUuid('branch_id')->constrained('branches');
            $table->foreignUuid('warehouse_id')->constrained('warehouses');
            $table->string('name');
            $table->enum('status', ['draft', 'counting', 'completed', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->foreignUuid('counted_by')->nullable()->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('stocktake_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('stocktake_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products');
            $table->foreignUuid('variant_id')->nullable()->constrained('product_variants');
            $table->decimal('system_qty', 10, 4)->default(0);
            $table->decimal('counted_qty', 10, 4)->nullable();
            $table->decimal('variance', 10, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['stocktake_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stocktake_items');
        Schema::dropIfExists('stocktakes');
    }
};
