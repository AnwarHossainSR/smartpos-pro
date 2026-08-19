<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('purchased_at');
            $table->timestamp('received_at')->nullable()->after('approved_at');
        });

        Schema::table('purchase_items', function (Blueprint $table) {
            $table->decimal('accepted_qty', 16, 4)->nullable()->after('quantity');
        });

        DB::table('purchase_items')
            ->whereNull('accepted_qty')
            ->update(['accepted_qty' => DB::raw('quantity')]);
    }

    public function down(): void
    {
        Schema::table('purchase_items', function (Blueprint $table) {
            $table->dropColumn('accepted_qty');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'received_at']);
        });
    }
};
