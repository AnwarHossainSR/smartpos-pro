<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * spatie/laravel-permission schema, with two deliberate deviations from the
 * package's stock migration:
 *
 *  - UUID keys throughout (`model_morph_key` = `model_uuid`), matching every
 *    other table in this schema.
 *  - Teams are ON with `team_foreign_key` = `tenant_id`. That is how role
 *    isolation between tenants is achieved: `roles` rows are tenant-scoped,
 *    `permissions` rows are global.
 *
 * Slugs live in `name` — the whole application (route middleware
 * `permission:products.create`, `hasPermission()`, the frontend `can()`) speaks
 * slugs, and Spatie keys everything off `name`. The human-readable label is the
 * separate `title` column.
 *
 * Table and column names are written literally rather than read out of
 * `config('permission.*')` so that `migrate:fresh` does not depend on config state.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');          // slug: products.create
            $table->string('guard_name');    // web
            $table->string('title');         // Create Products
            $table->string('module');        // products
            $table->string('action');        // create, view, update, delete, export, approve, refund, discount, price_override
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
            $table->index('module');
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');          // slug: cashier
            $table->string('guard_name');    // web
            $table->string('title');         // Cashier
            $table->string('description')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index('tenant_id', 'roles_tenant_foreign_key_index');
            $table->unique(['tenant_id', 'name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_uuid');
            $table->uuid('tenant_id');       // NOT NULL — part of the primary key
            $table->index(['model_uuid', 'model_type'], 'model_has_permissions_model_id_model_type_index');
            $table->index('tenant_id', 'model_has_permissions_tenant_foreign_key_index');
            $table->primary(['tenant_id', 'permission_id', 'model_uuid', 'model_type'],
                'model_has_permissions_permission_model_type_primary');
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->string('model_type');
            $table->uuid('model_uuid');
            $table->uuid('tenant_id');       // NOT NULL — part of the primary key
            // Branch-scoped role grants: a user can hold a role at one branch only.
            $table->foreignUuid('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['model_uuid', 'model_type'], 'model_has_roles_model_id_model_type_index');
            $table->index('tenant_id', 'model_has_roles_tenant_foreign_key_index');
            $table->primary(['tenant_id', 'role_id', 'model_uuid', 'model_type'],
                'model_has_roles_role_model_type_primary');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id'], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('model_has_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
