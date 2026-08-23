<?php

use App\Http\Controllers\Api\V1\CacheController;
use App\Http\Controllers\Api\V1\SuperAdmin\AuditLogController;
use App\Http\Controllers\Api\V1\SuperAdmin\ImpersonationController;
use App\Http\Controllers\Api\V1\SuperAdmin\PlanController;
use App\Http\Controllers\Api\V1\SuperAdmin\SupportController;
use App\Http\Controllers\Api\V1\SuperAdmin\TenantController;
use App\Http\Controllers\Api\V1\SuperAdmin\UserController;
use App\Http\Controllers\Api\V1\SuperAdminController;

Route::prefix('super')->middleware(['auth:sanctum'])->group(function () {
    Route::middleware('permission:super-admin.tenants.view')->group(function () {
        Route::get('tenants', [TenantController::class, 'index']);
        Route::get('tenants/{tenant}', [TenantController::class, 'show']);
        Route::get('metrics', [TenantController::class, 'metrics']);
        Route::get('metrics/revenue', [SuperAdminController::class, 'revenueMetrics']);
        Route::get('health', [SuperAdminController::class, 'health']);
        Route::get('impersonations', [ImpersonationController::class, 'activeImpersonations']);
    });

    Route::middleware('permission:super-admin.tenants.manage')->group(function () {
        Route::post('tenants', [TenantController::class, 'store']);
        Route::put('tenants/{tenant}', [TenantController::class, 'update']);
        Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend']);
        Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate']);
        Route::delete('tenants/{tenant}', [TenantController::class, 'destroy']);
    });

    Route::middleware('permission:super-admin.impersonate')->group(function () {
        Route::post('tenants/{tenant}/impersonate', [ImpersonationController::class, 'impersonate']);
        Route::post('impersonation/{impersonation}/revoke', [ImpersonationController::class, 'revokeImpersonation']);
        Route::post('impersonate', [ImpersonationController::class, 'startImpersonation']);
        Route::delete('impersonate', [ImpersonationController::class, 'stopImpersonation']);
        Route::post('impersonation/stop', [ImpersonationController::class, 'stopImpersonation']);
    });

    Route::middleware('permission:super-admin.plans.manage')->group(function () {
        Route::get('plans', [PlanController::class, 'listPlans']);
        Route::post('plans', [PlanController::class, 'storePlan']);
        Route::put('plans/{id}', [PlanController::class, 'updatePlan']);
        Route::delete('plans/{id}', [PlanController::class, 'destroyPlan']);
        Route::post('tenants/{tenantId}/assign-plan', [PlanController::class, 'assignPlan']);
    });

    Route::middleware('permission:super-admin.support.view')->group(function () {
        Route::get('support/tickets', [SupportController::class, 'index']);
        Route::get('support/tickets/metrics', [SupportController::class, 'metrics']);
        Route::get('support/agents', [SupportController::class, 'agents']);
        Route::get('support/tickets/{id}', [SupportController::class, 'show']);
    });

    Route::middleware('permission:super-admin.support.manage')->group(function () {
        Route::put('support/tickets/{id}/status', [SupportController::class, 'updateStatus']);
        Route::put('support/tickets/{id}/assign', [SupportController::class, 'assign']);
        Route::put('support/tickets/{id}/unassign', [SupportController::class, 'unassign']);
        Route::post('support/tickets/{id}/notes', [SupportController::class, 'addInternalNote']);
    });

    Route::middleware('permission:super-admin.audit.view')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'auditLogs']);
    });

    Route::middleware('permission:super-admin.users.manage')->group(function () {
        Route::get('users', [UserController::class, 'indexUsers']);
        Route::get('users/{user}', [UserController::class, 'showUser']);
        Route::put('users/{user}/status', [UserController::class, 'updateUserStatus']);
        Route::post('users/{user}/force-logout', [UserController::class, 'forceLogoutUser']);
    });
});

Route::get('cache/flush', [CacheController::class, 'flush'])
    ->middleware(['auth:sanctum', 'permission:super-admin.tenants.view']);
