<?php

namespace Database\Migrations;

use Illuminate\Database\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddStripeColumnsToTenantsAndSales extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_price_id')->nullable();
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('payment_url_token')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
            $table->dropColumn('stripe_price_id');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('payment_url_token');
        });
    }
}
