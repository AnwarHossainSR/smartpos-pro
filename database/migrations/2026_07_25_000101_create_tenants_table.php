<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('plan')->default('free'); // free, starter, professional, business, enterprise
            $table->string('status')->default('active'); // active, suspended, trialing, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('feature_limits')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
