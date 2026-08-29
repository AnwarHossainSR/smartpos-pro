<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            // An impersonated session acts with the tenant owner's own token, so every
            // entry it writes carried the owner's `user_id` and nothing else. Support
            // and the customer were indistinguishable in the record.
            $table->foreignUuid('impersonated_by')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();

            // The platform audit view queries across tenants by action and by actor,
            // neither of which the existing (tenant_id, created_at) index can serve.
            $table->index(['action', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropIndex(['action', 'created_at']);
            $table->dropConstrainedForeignId('impersonated_by');
        });
    }
};
