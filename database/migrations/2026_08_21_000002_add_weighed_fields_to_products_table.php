<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_weighed')->default(false);
            $table->string('weight_unit', 2)->default('kg');
            $table->decimal('price_per_unit', 12, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_weighed', 'weight_unit', 'price_per_unit']);
        });
    }
};
