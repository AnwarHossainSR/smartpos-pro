<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->string('payload_hash')->nullable()->after('path');
            $table->dropUnique(['tenant_id', 'key']);
            $table->unique(['tenant_id', 'key', 'method', 'path'], 'idempotency_keys_scoped_unique');
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            $table->dropUnique('idempotency_keys_scoped_unique');
            $table->unique(['tenant_id', 'key']);
            $table->dropColumn('payload_hash');
        });
    }
};
