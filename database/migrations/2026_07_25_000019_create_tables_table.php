<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('active_sale_id')->nullable()->constrained('sales')->nullOnDelete();
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
