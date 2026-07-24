<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('retail'); // retail, supermarket, pharmacy, restaurant, wholesale
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('tax_number')->nullable();
            $table->string('currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');
            $table->string('logo_path')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
