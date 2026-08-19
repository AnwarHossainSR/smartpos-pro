<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateModifiersAndProductModifiersTables extends Migration
{
    public function up(): void
    {
        // Modifiers table
        Schema::create('modifiers', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->json('options'); // [{label, price_delta}]
            $table->timestamps();

            $table->index(['tenant_id', 'name']);
        });

        // Product_modifier pivot table
        Schema::create('product_modifier', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('product_id');
            $table->uuid('modifier_id');
            $table->decimal('price_delta', 10, 2)->default(0);
            $table->timestamps();

            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('modifier_id')->constrained('modifiers')->onDelete('cascade');

            $table->index(['tenant_id', 'product_id']);
        });

        // Add modifiers_snapshot column to sale_items
        Schema::table('sale_items', function (Blueprint $table) {
            $table->json('modifiers_snapshot')->nullable()->default('[]');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            $table->dropColumn('modifiers_snapshot');
        });

        Schema::drop('product_modifier');

        Schema::drop('modifiers');
    }
}
