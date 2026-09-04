<?php

use App\Http\Controllers\Api\V1\SuperAdmin\AuditLogController as SuperAuditLogController;
use App\Http\Controllers\Api\V1\SuperAdmin\BackupController;
use App\Http\Controllers\Api\V1\SuperAdmin\ImpersonationController as SuperImpersonationController;
use App\Http\Controllers\Api\V1\SuperAdmin\PlanController as SuperPlanController;
use App\Http\Controllers\Api\V1\SuperAdmin\SettingController;
use App\Http\Controllers\Api\V1\SuperAdmin\SupportController as SuperSupportController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantController as SuperTenantController;
use App\Http\Controllers\Api\V1\SuperAdmin\UserController as SuperUserController;
use App\Http\Controllers\Api\V1\SuperAdminController;
use Illuminate\Support\Facades\Route;

/**
 * Platform-operator (super-admin) endpoints. These run under their own guard and
 * are deliberately *not* tenant-scoped.
 *
 * This block is the remainder of the old routes/api.php: the T-route-cleanup split
 * moved the tenant-facing routes into app.php / auth.php / portal.php but left the
 * super-admin group behind, so api.php stayed registered and shadowed 173 of the
 * routes the split had just created. It is extracted here so each route is declared
 * exactly once.
 */
Route::prefix('v1')->group(function () {

    // Called from *inside* an impersonated session (the tenant owner's own token, not a
    // super-admin token) — deliberately outside the super_admin-gated group below.
    Route::post('super/impersonation/stop', [SuperImpersonationController::class, 'stopImpersonation'])
        ->middleware(['auth:sanctum', 'ensure.active']);

    // T31-T33 — Super-admin (separate guard, no tenant scope)
    Route::prefix('super')->middleware(['auth:sanctum', 'ensure.active', 'super_admin'])->group(function () {
        Route::get('tenants', [SuperTenantController::class, 'index']);
        Route::post('tenants', [SuperTenantController::class, 'store']);
        Route::get('tenants/{tenant}', [SuperTenantController::class, 'show']);
        Route::put('tenants/{tenant}', [SuperTenantController::class, 'update']);
        Route::post('tenants/{tenant}/suspend', [SuperTenantController::class, 'suspend']);
        Route::post('tenants/{tenant}/activate', [SuperTenantController::class, 'activate']);
        Route::delete('tenants/{tenant}', [SuperTenantController::class, 'destroy']);

        // T32
        Route::get('metrics', [SuperTenantController::class, 'metrics']);
        Route::get('metrics/revenue', [SuperAdminController::class, 'revenueMetrics']);

        // T33
        Route::post('tenants/{tenant}/impersonate', [SuperImpersonationController::class, 'impersonate']);
        Route::post('impersonation/{impersonation}/revoke', [SuperImpersonationController::class, 'revokeImpersonation']);
        Route::get('impersonations', [SuperImpersonationController::class, 'activeImpersonations']);

        // System health
        Route::get('health', [SuperAdminController::class, 'health']);

        // Super-admin user management
        Route::get('users', [SuperUserController::class, 'indexUsers']);
        Route::get('users/{id}', [SuperUserController::class, 'showUser']);
        Route::put('users/{id}/status', [SuperUserController::class, 'updateUserStatus']);
        Route::post('users/{id}/force-logout', [SuperUserController::class, 'forceLogoutUser']);

        // Super-admin audit logs
        Route::get('audit-logs', [SuperAuditLogController::class, 'auditLogs']);

        // Super-admin plan management
        Route::get('plans', [SuperPlanController::class, 'listPlans']);
        Route::post('plans', [SuperPlanController::class, 'storePlan']);
        Route::put('plans/{id}', [SuperPlanController::class, 'updatePlan']);
        Route::delete('plans/{id}', [SuperPlanController::class, 'destroyPlan']);
        Route::post('tenants/{tenantId}/assign-plan', [SuperPlanController::class, 'assignPlan']);

        // Super-admin support tickets
        Route::get('support/tickets', [SuperSupportController::class, 'index']);
        Route::get('support/tickets/metrics', [SuperSupportController::class, 'metrics']);
        Route::get('support/tickets/{id}', [SuperSupportController::class, 'show']);
        Route::put('support/tickets/{id}/status', [SuperSupportController::class, 'updateStatus']);
        Route::post('support/tickets/{id}/reply', [SuperSupportController::class, 'addReply']);
        Route::post('support/tickets/{id}/notes', [SuperSupportController::class, 'addInternalNote']);

        // Platform settings
        Route::get('settings', [SettingController::class, 'index']);
        Route::put('settings', [SettingController::class, 'update']);
        Route::get('settings/tenant-overrides', [SettingController::class, 'tenantOverrides']);
        Route::put('settings/tenant-overrides', [SettingController::class, 'updateTenantOverrides']);

        // Database backups
        Route::get('backups', [BackupController::class, 'index']);
        Route::post('backups', [BackupController::class, 'store']);
        Route::get('backups/{backup}/download', [BackupController::class, 'download']);
        Route::delete('backups/{backup}', [BackupController::class, 'destroy']);
    });
});
