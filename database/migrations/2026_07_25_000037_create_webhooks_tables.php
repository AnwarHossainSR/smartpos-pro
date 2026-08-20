<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            $table->json('events');
            $table->string('secret');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Every list endpoint runs `where tenant_id = ? order by created_at limit n`.
            // Without this the whole tenant's rows are read and filesorted to return
            // one page.
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->json('payload');
            $table->integer('response_code')->nullable();
            $table->text('response_body')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamps();

            $table->index('webhook_id');
            $table->index('event');
            $table->index('delivered_at');

            // Every list endpoint runs `where tenant_id = ? order by created_at limit n`.
            // Without this the whole tenant's rows are read and filesorted to return
            // one page.
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
    }
};
