<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printer_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unique('id');
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->enum('connection_type', ['usb', 'network', 'serial']);
            $table->string('ip_address')->nullable();
            $table->unsignedSmallInteger('port')->nullable();
            $table->unsignedSmallInteger('paper_width')->default(80);
            $table->boolean('auto_cut')->default(true);
            $table->boolean('open_drawer')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printer_profiles');
    }
};
