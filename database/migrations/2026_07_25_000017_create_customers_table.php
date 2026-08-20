<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('customer_group')->nullable();
            $table->string('tax_number')->nullable();
            $table->boolean('tax_exempt')->default(false);
            $table->string('tax_exemption_reference')->nullable();
            // Structured address parts, matched against tax_zones under destination sourcing.
            $table->string('country_code', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('postcode')->nullable();
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('balance', 14, 2)->default(0); // outstanding dues (positive = owes us)
            $table->integer('loyalty_points')->default(0);
            $table->date('birthday')->nullable();
            $table->boolean('is_active')->default(true);
            // Short-lived bearer token for the passwordless customer portal.
            $table->string('portal_token')->nullable()->unique();
            $table->timestamp('portal_token_expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id']);
            $table->index(['tenant_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
