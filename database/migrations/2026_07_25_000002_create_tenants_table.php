<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('base_currency', 3)->default('USD');
            $table->decimal('fallback_exchange_rate', 12, 6)->default(1.0);
            $table->string('slug')->unique();
            $table->string('plan')->default('free'); // free, starter, professional, business, enterprise
            $table->string('status')->default('active'); // active, suspended, trialing, cancelled
            $table->timestamp('trial_ends_at')->nullable();
            $table->json('feature_limits')->nullable();
            // The per-tenant invoice counter. It lives on the tenant row (not on a
            // separate sequence table) so a checkout can take a single
            // `SELECT ... FOR UPDATE` on it and hold that lock for the rest of the
            // transaction — which is what makes the numbering gapless under
            // concurrent checkouts.
            $table->unsignedBigInteger('invoice_sequence')->default(0);
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
