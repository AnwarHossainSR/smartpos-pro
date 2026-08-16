<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name'); // pcs, kg, box
            $table->foreignUlid('base_unit_id', 12)->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('operator_value', 12, 4)->default(1); // conversion factor to base unit
            $table->timestamps();

            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
