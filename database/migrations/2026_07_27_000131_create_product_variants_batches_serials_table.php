<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Product attributes (e.g. "Color", "Size")
        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('name');         // e.g. "Color"
            $table->string('slug');
            $table->timestamps();
            $table->unique(['tenant_id', 'slug']);
        });

        // Attribute values (e.g. "Red", "XL")
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_attribute_id')->constrained('product_attributes')->cascadeOnDelete();
            $table->string('value');
            $table->timestamps();
        });

        // Product variants (combination of attribute values)
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();
            $table->decimal('purchase_price', 12, 4)->nullable();
            $table->decimal('selling_price', 12, 4)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Which attribute values make up each variant
        Schema::create('product_variant_attributes', function (Blueprint $table) {
            $table->foreignId('variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')->constrained('product_attribute_values')->cascadeOnDelete();
            $table->primary(['variant_id', 'attribute_value_id']);
        });

        // Stock batches (track batch numbers and expiry)
        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('batch_number');
            $table->date('manufactured_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('quantity', 12, 4)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'product_id', 'warehouse_id']);
            $table->index(['tenant_id', 'expires_at']);
        });

        // Serial numbers (for high-value, individually tracked items)
        Schema::create('stock_serials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->foreignId('product_id')->constrained('products');
            $table->foreignId('variant_id')->nullable()->constrained('product_variants');
            $table->foreignId('warehouse_id')->constrained('warehouses');
            $table->string('serial_number');
            $table->enum('status', ['in_stock', 'sold', 'returned', 'damaged'])->default('in_stock');
            $table->foreignId('sale_id')->nullable()->constrained('sales');
            $table->timestamps();

            $table->unique(['tenant_id', 'serial_number']);
            $table->index(['tenant_id', 'product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_serials');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('product_variant_attributes');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_attribute_values');
        Schema::dropIfExists('product_attributes');
    }
};
