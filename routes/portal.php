<?php

use App\Http\Controllers\Api\V1\CustomerPortalController;
use App\Http\Controllers\Api\V1\CustomerPortalDataController;

Route::post('portal/magic-link', [CustomerPortalController::class, 'magicLink'])
    ->middleware('throttle:login');
Route::get('portal/verify/{token}', [CustomerPortalController::class, 'verify']);

Route::middleware(['auth:portal'])->prefix('portal')->group(function () {
    Route::get('me', [CustomerPortalDataController::class, 'me']);
    Route::get('invoices', [CustomerPortalDataController::class, 'invoices']);
    Route::get('invoices/{sale}', [CustomerPortalDataController::class, 'invoiceDetail']);
    Route::post('pay/{sale}', [CustomerPortalDataController::class, 'pay']);
});
