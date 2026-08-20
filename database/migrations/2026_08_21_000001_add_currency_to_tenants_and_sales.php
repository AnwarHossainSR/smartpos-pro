<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('base_currency', 3)->default('USD')->after('name');
            $table->decimal('fallback_exchange_rate', 12, 6)->default(1.0)->after('base_currency');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->string('tender_currency', 3)->nullable()->after('due_amount');
            $table->decimal('tender_amount', 12, 2)->nullable()->after('tender_currency');
            $table->decimal('fx_rate', 12, 6)->nullable()->after('tender_amount');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['tender_currency', 'tender_amount', 'fx_rate']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['base_currency', 'fallback_exchange_rate']);
        });
    }
};
