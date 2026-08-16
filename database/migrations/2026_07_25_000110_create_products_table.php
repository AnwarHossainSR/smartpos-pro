<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('brand_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('unit_id', 12)->nullable()->constrained()->nullOnDelete();
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
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->boolean('tax_inclusive')->default(false);
            $table->decimal('min_stock_level', 14, 4)->default(0);
            $table->decimal('max_stock_level', 14, 4)->nullable();
            $table->decimal('reorder_quantity', 14, 4)->default(0);
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
            $table->index(['tenant_id', 'barcode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
