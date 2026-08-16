<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impersonations', function (Blueprint $table) {
            $table->char('id', 12)->primary();
            $table->unique('id');
            $table->foreignUlid('tenant_id', 12)->constrained()->cascadeOnDelete();
            $table->foreignUlid('super_admin_id', 12)->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('target_user_id', 12)->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('personal_access_token_id', 12)->nullable()->constrained('personal_access_tokens')->nullOnDelete();
            $table->string('reason');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignUlid('ended_by', 12)->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonations');
    }
};
