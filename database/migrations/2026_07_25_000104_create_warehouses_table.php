<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('business_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'business_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
