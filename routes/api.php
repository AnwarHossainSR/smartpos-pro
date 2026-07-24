<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SaleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ---- Public ----
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    // ---- Authenticated + tenant-scoped ----
    Route::middleware(['auth:sanctum', 'tenant', 'ensure.tenant'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('dashboard/summary', [DashboardController::class, 'summary'])
            ->middleware('permission:dashboard.view');

        // Catalog
        Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:products.view');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:products.create');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('permission:products.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:products.delete');

        Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

        // Inventory
        Route::get('inventory/balances', [InventoryController::class, 'index'])->middleware('permission:inventory.view');
        Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.update');

        // Customers
        Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
        Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

        // Sales / POS
        Route::get('sales', [SaleController::class, 'index'])->middleware('permission:sales.view');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->middleware('permission:sales.view');
        Route::post('sales', [SaleController::class, 'store'])->middleware(['permission:pos.create', 'idempotency']);
    });
});
