<?php

use App\Http\Controllers\Api\V1\CacheController;
use App\Http\Controllers\Api\V1\SuperAdminController;

Route::prefix('super')->middleware(['auth:sanctum'])->group(function () {
    Route::middleware('permission:super-admin.tenants.view')->group(function () {
        Route::get('tenants', [SuperAdminController::class, 'indexTenants']);
        Route::get('tenants/{tenant}', [SuperAdminController::class, 'showTenant']);
        Route::get('metrics', [SuperAdminController::class, 'metrics']);
        Route::get('metrics/revenue', [SuperAdminController::class, 'revenueMetrics']);
        Route::get('health', [SuperAdminController::class, 'health']);
        Route::get('impersonations', [SuperAdminController::class, 'activeImpersonations']);
    });

    Route::middleware('permission:super-admin.tenants.manage')->group(function () {
        Route::post('tenants', [SuperAdminController::class, 'store']);
        Route::put('tenants/{tenant}', [SuperAdminController::class, 'update']);
        Route::post('tenants/{tenant}/suspend', [SuperAdminController::class, 'suspendTenant']);
        Route::post('tenants/{tenant}/activate', [SuperAdminController::class, 'activateTenant']);
        Route::delete('tenants/{tenant}', [SuperAdminController::class, 'deleteTenant']);
    });

    Route::middleware('permission:super-admin.impersonate')->group(function () {
        Route::post('tenants/{tenant}/impersonate', [SuperAdminController::class, 'impersonate']);
        Route::post('impersonation/{impersonation}/revoke', [SuperAdminController::class, 'revokeImpersonation']);
        Route::post('impersonate', [SuperAdminController::class, 'startImpersonation']);
        Route::delete('impersonate', [SuperAdminController::class, 'stopImpersonation']);
        Route::post('impersonation/stop', [SuperAdminController::class, 'stopImpersonation']);
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
