<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('retail'); // retail, supermarket, pharmacy, restaurant, wholesale
            $table->string('legal_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('tax_number')->nullable();
            $table->foreignUuid('tax_profile_id')->nullable()->constrained()->nullOnDelete();
            // Denormalised from the profile so a tenant can override the default
            // for its own market without forking the profile.
            $table->string('price_display')->default('exclusive');
            $table->string('currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');
            $table->string('date_format', 20)->nullable()->default('Y-m-d');
            // Media paths, not URLs — the bucket a path resolves against is an
            // environment concern, so the URL is built at read time by
            // App\Services\Media\MediaService rather than frozen into a row.
            $table->string('logo_path')->nullable();
            $table->string('logo_dark_path')->nullable();
            $table->string('favicon_path')->nullable();
            // Sized for an 80mm thermal roll; kept apart from `logo_path` because a
            // colour logo prints as mud on a 1-bit printer.
            $table->string('receipt_logo_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->json('onboarding_state')->nullable();
            // Whether a sale/purchase/expense posts its journal entry automatically
            // (see App\Services\Accounting\JournalService).
            $table->boolean('auto_post_journals')->default(true);
            $table->string('kitchen_token')->nullable();
            $table->integer('audit_retention_days')->default(180);
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
