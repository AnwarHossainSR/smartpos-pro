<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Idempotency keys guarantee sensitive write operations (e.g. POS checkout)
        // execute at most once even if the client retries.
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            // Widths are sized deliberately, not left at the default 255: all four
            // columns below share one unique index, and MySQL caps an InnoDB key at
            // 3072 bytes. A CHAR(36) uuid plus three utf8mb4 varchar(255) columns
            // comes to 3204 and the migration fails outright. A client-generated key
            // is a ULID or a uuid, a method is an HTTP verb, and neither needs 255.
            $table->string('key', 128);
            $table->string('method', 10);
            $table->string('path');
            // Hash of the request body, so a replayed key carrying different input
            // can be rejected rather than silently served the first response.
            $table->string('payload_hash')->nullable();
            $table->integer('response_status')->nullable();
            $table->json('response_body')->nullable();
            $table->timestamps();

            // A key is only unique for the endpoint it was issued against: the same
            // client-generated key may legitimately be replayed at a different route.
            $table->unique(['tenant_id', 'key', 'method', 'path'], 'idempotency_keys_scoped_unique');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableUuidMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('notifications_center', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_center');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('idempotency_keys');
    }
};
