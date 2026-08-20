<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('unit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('tax_group_id')->nullable()->constrained()->nullOnDelete();
            // Who the reorder suggestion raises a purchase against.
            $table->foreignUuid('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->string('type')->default('standard'); // standard, variant, service, digital, combo
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->decimal('purchase_price', 14, 2)->default(0);
            $table->decimal('selling_price', 14, 2)->default(0);
            $table->decimal('wholesale_price', 14, 2)->nullable();
            // Legacy single-rate mirror, kept for the frontend. The tax engine reads
            // tax_group_id, never these two.
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('tax_inclusive')->default(false);
            // Scale items: sold by weight, priced per weight_unit rather than per piece.
            $table->boolean('is_weighed')->default(false);
            $table->string('weight_unit', 2)->default('kg');
            $table->decimal('price_per_unit', 12, 2)->nullable();
            $table->decimal('min_stock_level', 14, 4)->default(0);
            $table->decimal('max_stock_level', 14, 4)->nullable();
            $table->decimal('reorder_quantity', 14, 4)->default(0);
            // Reorder forecasting inputs (see App\Services\Inventory\ReorderSuggestionService).
            $table->integer('reorder_point')->default(0);
            $table->integer('reorder_lead_time_days')->default(7);
            $table->decimal('avg_daily_sales', 12, 2)->default(0);
            $table->boolean('track_inventory')->default(true);
            $table->boolean('track_batches')->default(false);
            $table->boolean('track_serials')->default(false);
            $table->boolean('track_expiry')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id']);
            $table->index(['tenant_id', 'is_active']);
            $table->unique(['tenant_id', 'sku']);
            // A barcode has to resolve to exactly one product for the POS scanner,
            // so this is a unique constraint rather than a plain lookup index.
            $table->unique(['tenant_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
