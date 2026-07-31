<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReturnController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SuperAdminController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\TwoFactorController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // ---- Public ----
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:login');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/2fa/challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:login');
    Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')->name('verification.verify');

    // ---- Authenticated + tenant-scoped ----
    Route::middleware(['auth:sanctum', 'tenant', 'ensure.tenant'])->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:login');
        Route::get('auth/sessions', [AuthController::class, 'sessions']);
        Route::delete('auth/sessions/{tokenId}', [AuthController::class, 'revokeSession']);
        Route::post('auth/sessions/revoke-all', [AuthController::class, 'revokeAllSessions']);

        Route::post('auth/2fa/enable', [TwoFactorController::class, 'enable']);
        Route::post('auth/2fa/confirm', [TwoFactorController::class, 'confirm']);
        Route::post('auth/2fa/disable', [TwoFactorController::class, 'disable']);
        Route::post('auth/2fa/recovery-codes/regenerate', [TwoFactorController::class, 'regenerateRecoveryCodes']);

        Route::get('dashboard/summary', [DashboardController::class, 'summary'])
            ->middleware('permission:dashboard.view');

        // Organization — branches
        Route::get('branches', [BranchController::class, 'index'])->middleware('permission:branches.view');
        Route::get('branches/{branch}', [BranchController::class, 'show'])->middleware('permission:branches.view');
        Route::post('branches', [BranchController::class, 'store'])->middleware('permission:branches.create');
        Route::put('branches/{branch}', [BranchController::class, 'update'])->middleware('permission:branches.update');
        Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:branches.delete');

        // Organization — warehouses
        Route::get('warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view');
        Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.view');
        Route::post('warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create');
        Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.update');
        Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete');

        // Catalog — categories
        Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:products.view');
        Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:products.create');
        Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('permission:products.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:products.delete');

        // Catalog — brands
        Route::get('brands', [BrandController::class, 'index'])->middleware('permission:products.view');
        Route::post('brands', [BrandController::class, 'store'])->middleware('permission:products.create');
        Route::put('brands/{brand}', [BrandController::class, 'update'])->middleware('permission:products.update');
        Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->middleware('permission:products.delete');

        // Catalog — units
        Route::get('units', [UnitController::class, 'index'])->middleware('permission:products.view');
        Route::post('units', [UnitController::class, 'store'])->middleware('permission:products.create');
        Route::put('units/{unit}', [UnitController::class, 'update'])->middleware('permission:products.update');
        Route::delete('units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:products.delete');

        // Catalog — products
        Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
        Route::get('products/barcode/{barcode}', [ProductController::class, 'findByBarcode'])->middleware('permission:products.view');
        Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
        Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create');
        Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');

        // Inventory
        Route::get('inventory/balances', [InventoryController::class, 'index'])->middleware('permission:inventory.view');
        Route::get('inventory/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view');
        Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.update');

        // Customers
        Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
        Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

        // Suppliers
        Route::get('suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view');
        Route::post('suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.create');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.delete');

        // Purchases
        Route::get('purchases', [PurchaseController::class, 'index'])->middleware('permission:purchases.view');
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('permission:purchases.view');
        Route::post('purchases', [PurchaseController::class, 'store'])->middleware(['permission:purchases.create', 'idempotency']);

        // Expenses
        Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->middleware('permission:expenses.view');
        Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->middleware('permission:expenses.create');
        Route::get('expenses', [ExpenseController::class, 'index'])->middleware('permission:expenses.view');
        Route::post('expenses', [ExpenseController::class, 'store'])->middleware('permission:expenses.create');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:expenses.update');
        Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->middleware('permission:expenses.approve');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete');

        // Sales / POS
        Route::get('sales', [SaleController::class, 'index'])->middleware('permission:sales.view');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->middleware('permission:sales.view');
        Route::get('sales/{sale}/invoice', [SaleController::class, 'invoice'])->middleware(['permission:sales.view', 'throttle:reports']);
        Route::post('sales', [SaleController::class, 'store'])->middleware(['permission:pos.create', 'idempotency']);
        Route::post('sales/{sale}/payments', [SaleController::class, 'addPayment'])->middleware(['permission:sales.update', 'idempotency']);

        // Sale returns / refunds
        Route::get('sale-returns', [SaleReturnController::class, 'index'])->middleware('permission:sales.view');
        Route::get('sale-returns/{saleReturn}', [SaleReturnController::class, 'show'])->middleware('permission:sales.view');
        Route::post('sales/{sale}/returns', [SaleReturnController::class, 'store'])->middleware(['permission:sales.refund', 'idempotency']);

        // Reports
        Route::middleware(['permission:reports.view', 'throttle:reports'])->prefix('reports')->group(function () {
            Route::get('sales-summary', [ReportController::class, 'salesSummary']);
            Route::get('sales-by-product', [ReportController::class, 'salesByProduct']);
            Route::get('profit', [ReportController::class, 'profit']);
            Route::get('inventory-valuation', [ReportController::class, 'inventoryValuation']);
            Route::get('low-stock', [ReportController::class, 'lowStock']);
            Route::get('dues', [ReportController::class, 'dues']);
        });

        // Accounting — chart of accounts
        Route::get('accounts', [AccountController::class, 'index'])->middleware('permission:accounting.view');
        Route::post('accounts', [AccountController::class, 'store'])->middleware('permission:accounting.create');
        Route::put('accounts/{account}', [AccountController::class, 'update'])->middleware('permission:accounting.update');

        // Accounting — double-entry journals & reports
        Route::get('journals', [JournalController::class, 'index'])->middleware('permission:accounting.view');
        Route::get('journals/trial-balance', [JournalController::class, 'trialBalance'])->middleware('permission:accounting.view');
        Route::get('journals/{journal}', [JournalController::class, 'show'])->middleware('permission:accounting.view');
        Route::post('journals', [JournalController::class, 'store'])->middleware('permission:accounting.create');
        Route::get('accounts/{account}/ledger', [JournalController::class, 'generalLedger'])->middleware('permission:accounting.view');

        // Cash register sessions
        Route::get('cash-registers', [CashRegisterController::class, 'index'])->middleware('permission:accounting.view');
        Route::post('cash-registers', [CashRegisterController::class, 'store'])->middleware('permission:accounting.create');
        Route::post('cash-registers/{cashRegister}/open', [CashRegisterController::class, 'open'])->middleware('permission:accounting.create');
        Route::get('cash-registers/{cashRegister}/current-session', [CashRegisterController::class, 'currentSession'])->middleware('permission:accounting.view');
        Route::get('cash-register-sessions/{session}', [CashRegisterController::class, 'show'])->middleware('permission:accounting.view');
        Route::get('cash-register-sessions/{session}/slip', [CashRegisterController::class, 'slip'])->middleware(['permission:accounting.view', 'throttle:reports']);
        Route::post('cash-register-sessions/{session}/movement', [CashRegisterController::class, 'movement'])->middleware('permission:accounting.create');
        Route::post('cash-register-sessions/{session}/close', [CashRegisterController::class, 'close'])->middleware('permission:accounting.create');

        // Team — users
        Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');
        Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
        Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create');
        Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');

        // Team — roles & permissions
        Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
        Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
        Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
        Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
        Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

        // Notifications (available to any authenticated tenant user)
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);

        // Audit trail (immutable — read-only)
        Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

        // Settings — business profile
        Route::get('settings/business', [SettingsController::class, 'showBusiness'])->middleware('permission:settings.view');
        Route::put('settings/business', [SettingsController::class, 'updateBusiness'])->middleware('permission:settings.update');
        Route::post('settings/onboarding/complete', [SettingsController::class, 'completeOnboarding'])->middleware('permission:settings.update');

        // Coupons & promotions
        Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:coupons.view');
        Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:coupons.create');
        Route::post('coupons/validate', [CouponController::class, 'validateCode'])->middleware('permission:pos.create');
        Route::put('coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:coupons.update');
        Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:coupons.delete');

        // Loyalty points
        Route::get('customers/{customer}/loyalty', [LoyaltyController::class, 'show'])->middleware('permission:loyalty.view');
        Route::post('customers/{customer}/loyalty/redeem', [LoyaltyController::class, 'redeem'])->middleware('permission:loyalty.manage');
        Route::post('customers/{customer}/loyalty/adjust', [LoyaltyController::class, 'adjust'])->middleware('permission:loyalty.manage');

        // Subscription & plan usage. Billing-sensitive: viewing/managing your own subscription
        // and usage requires a verified email; the public plan catalogue does not.
        Route::get('subscription/plans', [SubscriptionController::class, 'plans'])->middleware('permission:subscriptions.view');
        Route::get('subscription/current', [SubscriptionController::class, 'current'])->middleware(['permission:subscriptions.view', 'verified']);
        Route::get('subscription', [SubscriptionController::class, 'current'])->middleware(['permission:subscriptions.view', 'verified']);
        Route::get('subscription/usage', [SubscriptionController::class, 'usage'])->middleware(['permission:subscriptions.view', 'verified']);

        // T28 — Stock transfers
        Route::get('stock-transfers', [StockTransferController::class, 'index'])->middleware('permission:inventory.view');
        Route::post('stock-transfers', [StockTransferController::class, 'store'])->middleware('permission:inventory.adjust');
        Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->middleware('permission:inventory.view');
        Route::post('stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve'])->middleware('permission:inventory.adjust');
        Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->middleware('permission:inventory.adjust');
        Route::post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->middleware('permission:inventory.adjust');

        // T29 — Variants, batches, serials
        Route::get('products/{product}/variants', [ProductVariantController::class, 'variants'])->middleware('permission:inventory.view');
        Route::get('stock/batches', [ProductVariantController::class, 'batches'])->middleware('permission:inventory.view');
        Route::post('stock/batches', [ProductVariantController::class, 'storeBatch'])->middleware('permission:inventory.adjust');
        Route::get('stock/serials', [ProductVariantController::class, 'serials'])->middleware('permission:inventory.view');
        Route::post('stock/serials', [ProductVariantController::class, 'storeSerial'])->middleware('permission:inventory.adjust');

        // T30 — Quotations
        Route::get('quotations', [QuotationController::class, 'index'])->middleware('permission:sales.view');
        Route::post('quotations', [QuotationController::class, 'store'])->middleware('permission:sales.create');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->middleware('permission:sales.view');
        Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->middleware('permission:sales.update');
        Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->middleware('permission:sales.delete');
        Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->middleware('permission:pos.create');
    });

    // Called from *inside* an impersonated session (the tenant owner's own token, not a
    // super-admin token) — deliberately outside the super_admin-gated group below.
    Route::post('super/impersonation/stop', [SuperAdminController::class, 'stopImpersonation'])
        ->middleware(['auth:sanctum']);

    // T31-T33 — Super-admin (separate guard, no tenant scope)
    Route::prefix('super')->middleware(['auth:sanctum', 'super_admin'])->group(function () {
        Route::get('tenants', [SuperAdminController::class, 'tenants']);
        Route::get('tenants/{tenant}', [SuperAdminController::class, 'showTenant']);
        Route::post('tenants/{tenant}/suspend', [SuperAdminController::class, 'suspendTenant']);
        Route::post('tenants/{tenant}/activate', [SuperAdminController::class, 'activateTenant']);
        Route::delete('tenants/{tenant}', [SuperAdminController::class, 'deleteTenant']);

        // T32
        Route::get('metrics/revenue', [SuperAdminController::class, 'revenueMetrics']);

        // T33
        Route::post('tenants/{tenant}/impersonate', [SuperAdminController::class, 'impersonate']);
        Route::post('impersonation/{impersonation}/revoke', [SuperAdminController::class, 'revokeImpersonation']);
        Route::get('impersonations', [SuperAdminController::class, 'activeImpersonations']);
    });
});
