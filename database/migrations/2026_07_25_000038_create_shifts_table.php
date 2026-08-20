<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('clock_in');
            $table->timestamp('clock_out')->nullable();
            $table->unsignedInteger('worked_minutes')->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status')->default('open'); // open, closed
            $table->timestamps();

            // The "one open shift per user" invariant is enforced in ShiftService (a
            // row-locked read inside the clock-in transaction), not by a partial unique
            // index: MySQL has no partial indexes at all and SQLite — which the test
            // suite runs on — implements them with different semantics, so the same
            // DDL cannot express the rule on both engines. This composite index is the
            // portable half: it makes the service's open-shift lookup a single seek.
            $table->index(['tenant_id', 'user_id', 'status']);
            $table->index(['tenant_id', 'clock_in']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
