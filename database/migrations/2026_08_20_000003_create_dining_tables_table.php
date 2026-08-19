<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDiningTablesTable extends Migration
{
    public function up(): void
    {
        Schema::create('dining_tables', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->uuid('branch_id')->nullable();
            $table->string('name');
            $table->string('area')->nullable();
            $table->integer('seats');
            $table->enum('status', ['available', 'occupied', 'reserved'])->default('available');
            $table->decimal('x', 10, 2)->nullable(); // table position
            $table->decimal('y', 10, 2)->nullable();
            $table->foreignId('active_sale_id')->nullable()->constrained('sales');
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id']);
            $table->unique(['tenant_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropForeign(['active_sale_id']);
        });

        Schema::drop('dining_tables');
    }
}
