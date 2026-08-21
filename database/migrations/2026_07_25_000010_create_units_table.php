<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('short_name'); // pcs, kg, box
            $table->foreignUuid('base_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('operator_value', 12, 4)->default(1); // conversion factor to base unit
            $table->timestamps();

            $table->index(['tenant_id']);

            // Every list endpoint runs `where tenant_id = ? order by name limit n`.
            // Without this the whole tenant's rows are read and filesorted to return
            // one page.
            $table->index(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
