<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('business_id')->constrained()->nullOnDelete();
            $table->string('phone')->nullable()->after('email');
            $table->string('avatar_path')->nullable()->after('phone');
            $table->string('status')->default('active')->after('avatar_path'); // active, invited, suspended
            $table->boolean('is_super_admin')->default(false)->after('status');
            $table->timestamp('last_login_at')->nullable();
            $table->softDeletes();

            $table->index(['tenant_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropConstrainedForeignId('business_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['phone', 'avatar_path', 'status', 'is_super_admin', 'last_login_at', 'deleted_at']);
        });
    }
};
