<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->integer('reorder_point')->default(0);
            $table->integer('reorder_lead_time_days')->default(7);
            $table->decimal('avg_daily_sales', 12, 2)->default(0);
            $table->foreignUuid('default_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['default_supplier_id']);
            $table->dropColumn(['reorder_point', 'reorder_lead_time_days', 'avg_daily_sales', 'default_supplier_id']);
        });
    }
};
