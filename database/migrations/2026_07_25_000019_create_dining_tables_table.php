<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Restaurant floor plan. `dining_tables` rather than `tables`, which is far too
 * generic a name to claim; App\Models\Table pins it with an explicit $table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->cascadeOnDelete();
            // `dining_tables` and `sales` reference each other, so this half of the
            // cycle is declared as a bare column here and constrained by the sales
            // migration.
            $table->uuid('active_sale_id')->nullable();
            $table->string('name');
            $table->string('area')->nullable();
            $table->integer('seats');
            $table->string('status')->default('available'); // available, occupied, reserved
            $table->decimal('x', 10, 2)->nullable(); // table position on the floor plan
            $table->decimal('y', 10, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'branch_id']);
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dining_tables');
    }
};
