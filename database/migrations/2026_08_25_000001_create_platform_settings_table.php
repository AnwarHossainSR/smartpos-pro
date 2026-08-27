<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('key');
        });

        $defaults = [
            ['key' => 'modules', 'value' => json_encode([
                'pos' => true,
                'inventory' => true,
                'accounting' => true,
                'hr' => true,
                'crm' => true,
                'reports' => true,
                'multi_branch' => true,
                'ecommerce' => false,
            ]), 'description' => 'Global module toggles'],
            ['key' => 'tenant_overrides', 'value' => json_encode([]), 'description' => 'Per-tenant module overrides'],
            ['key' => 'maintenance_mode', 'value' => json_encode(false), 'description' => 'Global maintenance mode'],
            ['key' => 'registration_enabled', 'value' => json_encode(true), 'description' => 'Allow new tenant registration'],
            ['key' => 'max_tenants', 'value' => json_encode(null), 'description' => 'Max tenants allowed (null = unlimited)'],
        ];

        foreach ($defaults as $row) {
            DB::table('platform_settings')->insert(array_merge($row, [
                'id' => Str::uuid(),
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
