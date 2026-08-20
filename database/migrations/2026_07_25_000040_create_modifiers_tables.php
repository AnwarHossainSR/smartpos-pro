<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modifiers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->json('options'); // [{label, price_delta}]
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'name']);
        });

        Schema::create('product_modifier', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('modifier_id')->constrained('modifiers')->cascadeOnDelete();
            $table->decimal('price_delta', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'product_id']);
            $table->unique(['product_id', 'modifier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_modifier');
        Schema::dropIfExists('modifiers');
    }
};
