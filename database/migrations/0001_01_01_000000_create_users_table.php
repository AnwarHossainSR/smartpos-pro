<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            // The tenant/business/branch columns are declared here but their foreign
            // keys are attached by the branches migration: `users` is Laravel's stock
            // first migration and has to run before any of the three targets exist.
            $table->uuid('tenant_id')->nullable();
            $table->uuid('business_id')->nullable();
            $table->uuid('branch_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('avatar_path')->nullable();
            // Per-user UI preference, shaped {"mode": "...", "palette": "..."}.
            // Nullable: a user who has never picked one falls back to the client default.
            $table->json('theme')->nullable();
            $table->string('status')->default('active'); // active, invited, suspended
            $table->boolean('is_super_admin')->default(false);
            $table->string('email_verification_code', 6)->nullable();
            $table->timestamp('email_verification_expires_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedSmallInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            // Supervisor approval PIN (hashed, never returned). Used at the POS to
            // authorise an over-limit discount, a price override or a void — see
            // App\Services\Pos\ApprovalService.
            $table->string('approval_pin')->nullable();
            $table->timestamp('approval_pin_set_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
