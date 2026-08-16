<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->string('name');
            $table->string('slug')->unique(); // e.g. products.create
            $table->string('module');         // e.g. products
            $table->string('action');         // create, view, update, delete, export, approve, refund, discount, price_override
            $table->timestamps();
        });

        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignUlid('permission_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('role_id', 12)->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignUlid('role_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('branch_id', 12)->nullable()->constrained()->nullOnDelete();
            $table->primary(['role_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
