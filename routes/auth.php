<?php

use App\Http\Controllers\Api\V1\ApprovalPinController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OrderStatusController;
use App\Http\Controllers\Api\V1\TwoFactorController;

Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:login');
Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('auth/2fa/challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:login');
Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:login');
Route::post('auth/email/verify', [AuthController::class, 'verifyEmailOtp'])->middleware('throttle:login');
Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')->name('verification.verify');

Route::get('orders/{token}', [OrderStatusController::class, 'show'])
    ->middleware('throttle:30,1')->name('orders.status');

Route::middleware(['auth:sanctum', 'tenant', 'ensure.tenant', 'error-tracking'])->group(function () {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::patch('auth/preferences', [AuthController::class, 'updatePreferences']);
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:login');
    Route::get('auth/sessions', [AuthController::class, 'sessions']);
    Route::delete('auth/sessions/{tokenId}', [AuthController::class, 'revokeSession']);
    Route::post('auth/sessions/revoke-all', [AuthController::class, 'revokeAllSessions']);

    Route::post('auth/change-password', [AuthController::class, 'changePassword']);
    Route::get('auth/2fa/status', [AuthController::class, 'twoFactorStatus']);
    Route::post('auth/2fa/enable', [TwoFactorController::class, 'enable']);
    Route::post('auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('auth/2fa/disable', [TwoFactorController::class, 'disable']);
    Route::post('auth/2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);
    Route::get('auth/lockout-status', [AuthController::class, 'lockoutStatus']);

    Route::put('auth/approval-pin', [ApprovalPinController::class, 'update'])->middleware('throttle:approval-pin');
    Route::delete('auth/approval-pin', [ApprovalPinController::class, 'destroy'])->middleware('throttle:approval-pin');
});
