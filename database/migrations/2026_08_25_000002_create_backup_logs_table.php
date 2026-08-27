<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backup_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('filename');
            $table->string('disk')->default('r2');
            $table->string('path');
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->uuid('created_by');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->cascadeOnDelete();
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_logs');
    }
};
