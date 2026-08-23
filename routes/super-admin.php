<?php

use App\Http\Controllers\Api\V1\CacheController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantController;
use App\Http\Controllers\Api\V1\SuperAdminController;

Route::prefix('super')->middleware(['auth:sanctum'])->group(function () {
    Route::middleware('permission:super-admin.tenants.view')->group(function () {
        Route::get('tenants', [TenantController::class, 'index']);
        Route::get('tenants/{tenant}', [TenantController::class, 'show']);
        Route::get('metrics', [TenantController::class, 'metrics']);
        Route::get('metrics/revenue', [SuperAdminController::class, 'revenueMetrics']);
        Route::get('health', [SuperAdminController::class, 'health']);
        Route::get('impersonations', [SuperAdminController::class, 'activeImpersonations']);
    });

    Route::middleware('permission:super-admin.tenants.manage')->group(function () {
        Route::post('tenants', [TenantController::class, 'store']);
        Route::put('tenants/{tenant}', [TenantController::class, 'update']);
        Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend']);
        Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate']);
        Route::delete('tenants/{tenant}', [TenantController::class, 'destroy']);
    });

    Route::middleware('permission:super-admin.impersonate')->group(function () {
        Route::post('tenants/{tenant}/impersonate', [SuperAdminController::class, 'impersonate']);
        Route::post('impersonation/{impersonation}/revoke', [SuperAdminController::class, 'revokeImpersonation']);
        Route::post('impersonate', [SuperAdminController::class, 'startImpersonation']);
        Route::delete('impersonate', [SuperAdminController::class, 'stopImpersonation']);
        Route::post('impersonation/stop', [SuperAdminController::class, 'stopImpersonation']);
    });

    Route::middleware('permission:super-admin.plans.manage')->group(function () {
        Route::get('plans', [SuperAdminController::class, 'listPlans']);
        Route::post('plans', [SuperAdminController::class, 'storePlan']);
        Route::put('plans/{id}', [SuperAdminController::class, 'updatePlan']);
        Route::delete('plans/{id}', [SuperAdminController::class, 'destroyPlan']);
        Route::post('tenants/{tenantId}/assign-plan', [SuperAdminController::class, 'assignPlan']);
    });

    Route::middleware('permission:super-admin.audit.view')->group(function () {
        Route::get('audit-logs', [SuperAdminController::class, 'auditLogs']);
    });

    Route::middleware('permission:super-admin.users.manage')->group(function () {
        Route::get('users', [SuperAdminController::class, 'indexUsers']);
        Route::get('users/{user}', [SuperAdminController::class, 'showUser']);
        Route::put('users/{user}/status', [SuperAdminController::class, 'updateUserStatus']);
        Route::post('users/{user}/force-logout', [SuperAdminController::class, 'forceLogoutUser']);
    });
});

Route::get('cache/flush', [CacheController::class, 'flush'])
    ->middleware(['auth:sanctum', 'permission:super-admin.tenants.view']);
