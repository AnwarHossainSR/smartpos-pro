<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddKitchenFieldsToSalesTable extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->enum('kitchen_status', ['pending', 'preparing', 'ready', 'served'])->nullable()->default('pending');
            $table->string('kitchen_note')->nullable();
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->string('kitchen_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('kitchen_status');
            $table->dropColumn('kitchen_note');
        });

        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn('kitchen_token');
        });
    }
}
