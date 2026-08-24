<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\ApprovalPinController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CouponController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeliveryController;
use App\Http\Controllers\Api\V1\EmailTemplateController;
use App\Http\Controllers\Api\V1\ExpenseCategoryController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\ForecastController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\JournalController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\PosApprovalController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\PrinterProfileController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductVariantController;
use App\Http\Controllers\Api\V1\PurchaseController;
use App\Http\Controllers\Api\V1\QuotationController;
use App\Http\Controllers\Api\V1\ReorderSuggestionController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SaleReturnController;
use App\Http\Controllers\Api\V1\SettingsController;
use App\Http\Controllers\Api\V1\ShiftController;
use App\Http\Controllers\Api\V1\StocktakeController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\StripeController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\SupportTicketController;
use App\Http\Controllers\Api\V1\TaxGroupController;
use App\Http\Controllers\Api\V1\TaxProfileController;
use App\Http\Controllers\Api\V1\TaxRateController;
use App\Http\Controllers\Api\V1\TaxReportController;
use App\Http\Controllers\Api\V1\TaxZoneController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Http\Controllers\Api\V1\ZReportController;
use App\Support\Media\BusinessMediaSlots;

Route::middleware(['auth:sanctum', 'tenant', 'ensure.tenant', 'error-tracking'])->group(function () {
    Route::post('me/avatar', [MediaController::class, 'uploadAvatar'])
        ->defaults('media_collection', 'user.avatar');
    Route::delete('me/avatar', [MediaController::class, 'destroyAvatar']);
    Route::get('media/collections', [MediaController::class, 'collections']);

    Route::get('health', HealthController::class)->middleware('permission:dashboard.view');

    Route::get('dashboard/summary', [DashboardController::class, 'summary'])
        ->middleware('permission:dashboard.view');

    Route::get('{module}/export', [ExportController::class, 'index'])
        ->where('module', 'products|inventory|sales|purchases|customers|suppliers|reports|accounting');

    Route::get('branches', [BranchController::class, 'index'])->middleware('permission:branches.view');
    Route::get('branches/{branch}', [BranchController::class, 'show'])->middleware('permission:branches.view');
    Route::post('branches', [BranchController::class, 'store'])->middleware('permission:branches.create');
    Route::put('branches/{branch}', [BranchController::class, 'update'])->middleware('permission:branches.update');
    Route::delete('branches/{branch}', [BranchController::class, 'destroy'])->middleware('permission:branches.delete');

    Route::get('warehouses', [WarehouseController::class, 'index'])->middleware('permission:warehouses.view');
    Route::get('warehouses/{warehouse}', [WarehouseController::class, 'show'])->middleware('permission:warehouses.view');
    Route::post('warehouses', [WarehouseController::class, 'store'])->middleware('permission:warehouses.create');
    Route::put('warehouses/{warehouse}', [WarehouseController::class, 'update'])->middleware('permission:warehouses.update');
    Route::delete('warehouses/{warehouse}', [WarehouseController::class, 'destroy'])->middleware('permission:warehouses.delete');

    Route::get('categories', [CategoryController::class, 'index'])->middleware('permission:products.view');
    Route::post('categories', [CategoryController::class, 'store'])->middleware('permission:products.create');
    Route::put('categories/{category}', [CategoryController::class, 'update'])->middleware('permission:products.update');
    Route::delete('categories/{category}', [CategoryController::class, 'destroy'])->middleware('permission:products.delete');

    Route::get('brands', [BrandController::class, 'index'])->middleware('permission:products.view');
    Route::post('brands', [BrandController::class, 'store'])->middleware('permission:products.create');
    Route::put('brands/{brand}', [BrandController::class, 'update'])->middleware('permission:products.update');
    Route::delete('brands/{brand}', [BrandController::class, 'destroy'])->middleware('permission:products.delete');
    Route::post('brands/{brand}/logo', [MediaController::class, 'uploadBrandLogo'])
        ->middleware('permission:products.update')
        ->defaults('media_collection', 'brand.logo');
    Route::delete('brands/{brand}/logo', [MediaController::class, 'destroyBrandLogo'])->middleware('permission:products.update');

    Route::get('units', [UnitController::class, 'index'])->middleware('permission:products.view');
    Route::post('units', [UnitController::class, 'store'])->middleware('permission:products.create');
    Route::put('units/{unit}', [UnitController::class, 'update'])->middleware('permission:products.update');
    Route::delete('units/{unit}', [UnitController::class, 'destroy'])->middleware('permission:products.delete');

    Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view');
    Route::get('products/import-template', [ProductController::class, 'template'])->middleware('permission:products.view');
    Route::post('products/import', [ProductController::class, 'import'])->middleware('permission:products.create');
    Route::get('products/barcode/{barcode}', [ProductController::class, 'findByBarcode'])->middleware('permission:products.view');
    Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view');
    Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create');
    Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete');
    Route::post('products/{product}/image', [ProductController::class, 'uploadImage'])
        ->middleware('permission:products.update')
        ->defaults('media_collection', 'product.image');
    Route::delete('products/{product}/image', [ProductController::class, 'deleteImage'])->middleware('permission:products.update');

    Route::get('inventory/balances', [InventoryController::class, 'index'])->middleware('permission:inventory.view');
    Route::get('inventory/movements', [InventoryController::class, 'movements'])->middleware('permission:inventory.view');
    Route::post('inventory/adjust', [InventoryController::class, 'adjust'])->middleware('permission:inventory.update');

    Route::get('customers', [CustomerController::class, 'index'])->middleware('permission:customers.view');
    Route::get('customers/{customer}', [CustomerController::class, 'show'])->middleware('permission:customers.view');
    Route::post('customers', [CustomerController::class, 'store'])->middleware('permission:customers.create');
    Route::put('customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:customers.update');
    Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:customers.delete');

    Route::post('customers/{customer}/anonymize', [CustomerController::class, 'anonymize'])->middleware('permission:customers.delete');

    Route::get('suppliers', [SupplierController::class, 'index'])->middleware('permission:suppliers.view');
    Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->middleware('permission:suppliers.view');
    Route::post('suppliers', [SupplierController::class, 'store'])->middleware('permission:suppliers.create');
    Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:suppliers.update');
    Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:suppliers.delete');

    Route::post('suppliers/{supplier}/payments', [SupplierController::class, 'storePayment'])->middleware('permission:suppliers.view');
    Route::get('suppliers/{supplier}/payments', [SupplierController::class, 'indexPayments'])->middleware('permission:suppliers.view');
    Route::get('suppliers/{supplier}/statement', [SupplierController::class, 'statement'])->middleware('permission:suppliers.view');

    Route::get('purchases', [PurchaseController::class, 'index'])->middleware('permission:purchases.view');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('permission:purchases.view');
    Route::post('purchases', [PurchaseController::class, 'store'])->middleware(['permission:purchases.create', 'idempotency']);
    Route::post('purchases/{purchase}/approve', [PurchaseController::class, 'approve'])->middleware('permission:purchases.approve');
    Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->middleware('permission:purchases.update');
    Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->middleware('permission:purchases.update');
    Route::put('purchases/{purchase}', [PurchaseController::class, 'edit'])->middleware('permission:purchases.edit');
    Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])->middleware('permission:purchases.delete');
    Route::post('purchases/{purchase}/return', [PurchaseController::class, 'return'])->middleware('permission:purchases.return');

    Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->middleware('permission:expenses.view');
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->middleware('permission:expenses.create');
    Route::get('expenses', [ExpenseController::class, 'index'])->middleware('permission:expenses.view');
    Route::post('expenses', [ExpenseController::class, 'store'])->middleware('permission:expenses.create');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:expenses.update');
    Route::post('expenses/{expense}/approve', [ExpenseController::class, 'approve'])->middleware('permission:expenses.approve');
    Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:expenses.delete');

    Route::get('sales', [SaleController::class, 'index'])->middleware('permission:sales.view');
    Route::get('sales/{sale}', [SaleController::class, 'show'])->middleware('permission:sales.view');
    Route::get('sales/{sale}/invoice', [SaleController::class, 'invoice'])->middleware(['permission:sales.view', 'throttle:reports']);
    Route::post('sales', [SaleController::class, 'store'])->middleware(['permission:pos.create', 'idempotency']);
    Route::post('sales/{sale}/payments', [SaleController::class, 'addPayment'])->middleware(['permission:sales.update', 'idempotency']);
    Route::post('sales/{sale}/email-status-link', [SaleController::class, 'emailStatusLink'])->middleware(['permission:sales.view', 'throttle:reports']);
    Route::post('sales/{sale}/resume', [SaleController::class, 'resume'])->middleware(['permission:pos.create', 'idempotency']);
        Route::post('sales/{sale}/void', [SaleController::class, 'void'])->middleware('permission:sales.delete');

        Route::post('sales/{sale}/deliveries', [DeliveryController::class, 'assign'])->middleware('permission:deliveries.create');
    Route::get('deliveries', [DeliveryController::class, 'index'])->middleware('permission:deliveries.view');
    Route::get('deliveries/{delivery}', [DeliveryController::class, 'show'])->middleware('permission:deliveries.view');
    Route::put('deliveries/{delivery}/status', [DeliveryController::class, 'updateStatus'])->middleware('permission:deliveries.update');

    Route::get('pos/bootstrap', [PosController::class, 'bootstrap'])->middleware('permission:pos.view');

    Route::post('pos/approvals', [PosApprovalController::class, 'store'])
        ->middleware(['permission:pos.create', 'throttle:approval-pin']);

    Route::get('sale-returns', [SaleReturnController::class, 'index'])->middleware('permission:sales.view');
    Route::get('sale-returns/{saleReturn}', [SaleReturnController::class, 'show'])->middleware('permission:sales.view');
    Route::post('sales/{sale}/returns', [SaleReturnController::class, 'store'])->middleware(['permission:sales.refund', 'idempotency']);

    Route::middleware(['permission:reports.view', 'throttle:reports'])->prefix('reports')->group(function () {
        Route::get('sales-summary', [ReportController::class, 'salesSummary']);
        Route::get('sales-by-product', [ReportController::class, 'salesByProduct']);
        Route::get('profit', [ReportController::class, 'profit']);
        Route::get('inventory-valuation', [ReportController::class, 'inventoryValuation']);
        Route::get('low-stock', [ReportController::class, 'lowStock']);
        Route::get('dues', [ReportController::class, 'dues']);
        Route::get('payables-aging', [ReportController::class, 'payablesAging']);
        Route::get('z-report', [ZReportController::class, 'show']);
        Route::get('z-report/pdf', [ZReportController::class, 'pdf']);
        Route::get('profit-loss', [ReportController::class, 'profitLoss']);
        Route::get('balance-sheet', [ReportController::class, 'balanceSheet']);
    });

    Route::middleware(['permission:taxes.view', 'throttle:reports'])->prefix('reports')->group(function () {
        Route::get('tax-summary', [TaxReportController::class, 'summary']);
        Route::get('tax-return', [TaxReportController::class, 'taxReturn']);
    });

    Route::get('tax-rates', [TaxRateController::class, 'index'])->middleware('permission:taxes.view');
    Route::post('tax-rates', [TaxRateController::class, 'store'])->middleware('permission:taxes.manage');
    Route::put('tax-rates/{taxRate}', [TaxRateController::class, 'update'])->middleware('permission:taxes.manage');
    Route::delete('tax-rates/{taxRate}', [TaxRateController::class, 'destroy'])->middleware('permission:taxes.manage');

    Route::get('tax-groups', [TaxGroupController::class, 'index'])->middleware('permission:taxes.view');
    Route::post('tax-groups', [TaxGroupController::class, 'store'])->middleware('permission:taxes.manage');
    Route::put('tax-groups/{taxGroup}', [TaxGroupController::class, 'update'])->middleware('permission:taxes.manage');
    Route::delete('tax-groups/{taxGroup}', [TaxGroupController::class, 'destroy'])->middleware('permission:taxes.manage');

    Route::get('tax-zones', [TaxZoneController::class, 'index'])->middleware('permission:taxes.view');
    Route::post('tax-zones', [TaxZoneController::class, 'store'])->middleware('permission:taxes.manage');
    Route::put('tax-zones/{taxZone}', [TaxZoneController::class, 'update'])->middleware('permission:taxes.manage');
    Route::delete('tax-zones/{taxZone}', [TaxZoneController::class, 'destroy'])->middleware('permission:taxes.manage');

    Route::get('tax-profiles', [TaxProfileController::class, 'index'])->middleware('permission:taxes.view');
    Route::get('tax-profiles/{taxProfile}', [TaxProfileController::class, 'show'])->middleware('permission:taxes.view');

    Route::get('accounts', [AccountController::class, 'index'])->middleware('permission:accounting.view');
    Route::post('accounts', [AccountController::class, 'store'])->middleware('permission:accounting.create');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->middleware('permission:accounting.update');

    Route::get('journals', [JournalController::class, 'index'])->middleware('permission:accounting.view');
    Route::get('journals/trial-balance', [JournalController::class, 'trialBalance'])->middleware('permission:accounting.view');
    Route::get('journals/{journal}', [JournalController::class, 'show'])->middleware('permission:accounting.view');
    Route::post('journals', [JournalController::class, 'store'])->middleware('permission:accounting.create');
    Route::get('accounts/{account}/ledger', [JournalController::class, 'generalLedger'])->middleware('permission:accounting.view');

    Route::get('cash-registers', [CashRegisterController::class, 'index'])->middleware('permission:accounting.view');
    Route::post('cash-registers', [CashRegisterController::class, 'store'])->middleware('permission:accounting.create');
    Route::post('cash-registers/{cashRegister}/open', [CashRegisterController::class, 'open'])->middleware('permission:accounting.create');
    Route::get('cash-registers/{cashRegister}/current-session', [CashRegisterController::class, 'currentSession'])->middleware('permission:accounting.view');
    Route::get('cash-register-sessions/{session}', [CashRegisterController::class, 'show'])->middleware('permission:accounting.view');
    Route::get('cash-register-sessions/{session}/slip', [CashRegisterController::class, 'slip'])->middleware(['permission:accounting.view', 'throttle:reports']);
    Route::post('cash-register-sessions/{session}/movement', [CashRegisterController::class, 'movement'])->middleware('permission:accounting.create');
    Route::post('cash-register-sessions/{session}/close', [CashRegisterController::class, 'close'])->middleware('permission:accounting.create');

    Route::get('users', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::post('users', [UserController::class, 'store'])->middleware('permission:users.create');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.delete');
    Route::delete('users/{user}/approval-pin', [ApprovalPinController::class, 'clearForUser'])->middleware('permission:users.update');

    Route::post('shifts/clock-in', [ShiftController::class, 'clockIn'])->middleware('permission:shifts.view');
    Route::post('shifts/clock-out', [ShiftController::class, 'clockOut'])->middleware('permission:shifts.view');
    Route::get('shifts/me', [ShiftController::class, 'me'])->middleware('permission:shifts.view');
    Route::get('shifts', [ShiftController::class, 'index'])->middleware('permission:shifts.view');
    Route::get('reports/shifts', [ShiftController::class, 'report'])->middleware(['permission:reports.view', 'throttle:reports']);

    Route::get('permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
    Route::get('roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
    Route::post('roles', [RoleController::class, 'store'])->middleware('permission:roles.create');
    Route::put('roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update');
    Route::delete('roles/{role}', [RoleController::class, 'destroy'])->middleware('permission:roles.delete');

    Route::get('notifications', [NotificationController::class, 'index']);
    Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead']);
    Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);

    Route::get('audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

    Route::get('email-templates', [EmailTemplateController::class, 'index'])->middleware('permission:settings.view');
    Route::get('email-templates/{key}', [EmailTemplateController::class, 'show'])->middleware('permission:settings.view');
    Route::put('email-templates/{key}', [EmailTemplateController::class, 'update'])->middleware('permission:settings.update');
    Route::delete('email-templates/{key}', [EmailTemplateController::class, 'destroy'])->middleware('permission:settings.update');
    Route::post('email-templates/{key}/preview', [EmailTemplateController::class, 'preview'])->middleware('permission:settings.view');
    Route::post('email-templates/{key}/test', [EmailTemplateController::class, 'sendTest'])->middleware(['permission:settings.update', 'throttle:reports']);

    Route::get('settings/business', [SettingsController::class, 'showBusiness'])->middleware('permission:settings.view');
    Route::put('settings/business', [SettingsController::class, 'updateBusiness'])->middleware('permission:settings.update');
    Route::post('settings/business/media/{slot}', [MediaController::class, 'uploadBusiness'])
        ->middleware('permission:settings.update')
        ->whereIn('slot', BusinessMediaSlots::keys());
    Route::delete('settings/business/media/{slot}', [MediaController::class, 'destroyBusiness'])
        ->middleware('permission:settings.update')
        ->whereIn('slot', BusinessMediaSlots::keys());
    Route::get('settings/retention', [SettingsController::class, 'retention'])->middleware('permission:settings.view');
    Route::get('settings/approvals', [SettingsController::class, 'approvals'])->middleware('permission:settings.view');
    Route::put('settings/approvals', [SettingsController::class, 'updateApprovals'])->middleware('permission:settings.update');
    Route::post('settings/onboarding/complete', [SettingsController::class, 'completeOnboarding'])->middleware('permission:settings.update');
    Route::post('settings/onboarding/step', [SettingsController::class, 'recordOnboardingStep'])->middleware('permission:settings.update');

    Route::get('printer-profiles', [PrinterProfileController::class, 'index'])->middleware('permission:settings.view');
    Route::post('printer-profiles', [PrinterProfileController::class, 'store'])->middleware('permission:settings.update');
    Route::put('printer-profiles/{printerProfile}', [PrinterProfileController::class, 'update'])->middleware('permission:settings.update');
    Route::delete('printer-profiles/{printerProfile}', [PrinterProfileController::class, 'destroy'])->middleware('permission:settings.update');

    Route::get('webhooks', [WebhookController::class, 'index'])->middleware('permission:settings.update');
    Route::post('webhooks', [WebhookController::class, 'store'])->middleware('permission:settings.update');
    Route::get('webhooks/{webhook}', [WebhookController::class, 'show'])->middleware('permission:settings.update');
    Route::put('webhooks/{webhook}', [WebhookController::class, 'update'])->middleware('permission:settings.update');
    Route::delete('webhooks/{webhook}', [WebhookController::class, 'destroy'])->middleware('permission:settings.update');
    Route::get('webhook-deliveries', [WebhookController::class, 'deliveries'])->middleware('permission:settings.update');
    Route::post('webhook-deliveries/{delivery}/retry', [WebhookController::class, 'retryDelivery'])->middleware('permission:settings.update');

    Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])->withoutMiddleware('auth:sanctum');
    Route::get('stripe/create-checkout-session', [StripeController::class, 'createCheckoutSession'])->middleware('permission:pos.create');
    Route::get('stripe/create-subscription-session', [StripeController::class, 'createSubscriptionSession'])->middleware('permission:pos.create');
    Route::get('pay/{token}', [StripeController::class, 'paymentStatus'])->name('stripe.payment.status');

    Route::get('coupons', [CouponController::class, 'index'])->middleware('permission:coupons.view');
    Route::post('coupons', [CouponController::class, 'store'])->middleware('permission:coupons.create');
    Route::post('coupons/validate', [CouponController::class, 'validateCode'])->middleware('permission:pos.create');
    Route::put('coupons/{coupon}', [CouponController::class, 'update'])->middleware('permission:coupons.update');
    Route::delete('coupons/{coupon}', [CouponController::class, 'destroy'])->middleware('permission:coupons.delete');

    Route::get('customers/{customer}/loyalty', [LoyaltyController::class, 'show'])->middleware('permission:loyalty.view');
    Route::post('customers/{customer}/loyalty/redeem', [LoyaltyController::class, 'redeem'])->middleware('permission:loyalty.manage');
    Route::post('customers/{customer}/loyalty/adjust', [LoyaltyController::class, 'adjust'])->middleware('permission:loyalty.manage');

    Route::get('subscription/plans', [SubscriptionController::class, 'plans'])->middleware('permission:subscriptions.view');
    Route::get('subscription/current', [SubscriptionController::class, 'current'])->middleware(['permission:subscriptions.view', 'verified']);
    Route::get('subscription', [SubscriptionController::class, 'current'])->middleware(['permission:subscriptions.view', 'verified']);
    Route::get('subscription/usage', [SubscriptionController::class, 'usage'])->middleware(['permission:subscriptions.view', 'verified']);
    Route::post('subscription/checkout', [SubscriptionController::class, 'checkout'])->middleware(['permission:subscriptions.manage', 'verified']);

    Route::get('stocktakes', [StocktakeController::class, 'index'])->middleware('permission:stocktakes.view');
    Route::post('stocktakes', [StocktakeController::class, 'store'])->middleware('permission:stocktakes.create');
    Route::get('stocktakes/{stocktake}', [StocktakeController::class, 'show'])->middleware('permission:stocktakes.view');
    Route::post('stocktakes/{stocktake}/start', [StocktakeController::class, 'start'])->middleware('permission:stocktakes.update');
    Route::post('stocktakes/{stocktake}/items/{item}/count', [StocktakeController::class, 'recordCount'])->middleware('permission:stocktakes.update');
    Route::post('stocktakes/{stocktake}/complete', [StocktakeController::class, 'complete'])->middleware('permission:stocktakes.update');
    Route::post('stocktakes/{stocktake}/cancel', [StocktakeController::class, 'cancel'])->middleware('permission:stocktakes.update');

    Route::get('stock-transfers', [StockTransferController::class, 'index'])->middleware('permission:inventory.view');
    Route::post('stock-transfers', [StockTransferController::class, 'store'])->middleware('permission:inventory.adjust');
    Route::get('stock-transfers/{stockTransfer}', [StockTransferController::class, 'show'])->middleware('permission:inventory.view');
    Route::post('stock-transfers/{stockTransfer}/approve', [StockTransferController::class, 'approve'])->middleware('permission:inventory.adjust');
    Route::post('stock-transfers/{stockTransfer}/receive', [StockTransferController::class, 'receive'])->middleware('permission:inventory.adjust');
    Route::post('stock-transfers/{stockTransfer}/cancel', [StockTransferController::class, 'cancel'])->middleware('permission:inventory.adjust');

    Route::get('products/{product}/variants', [ProductVariantController::class, 'variants'])->middleware('permission:inventory.view');
    Route::get('stock/batches', [ProductVariantController::class, 'batches'])->middleware('permission:inventory.view');
    Route::post('stock/batches', [ProductVariantController::class, 'storeBatch'])->middleware('permission:inventory.adjust');
    Route::get('stock/serials', [ProductVariantController::class, 'serials'])->middleware('permission:inventory.view');
    Route::post('stock/serials', [ProductVariantController::class, 'storeSerial'])->middleware('permission:inventory.adjust');

    Route::get('reorder-suggestions', [ReorderSuggestionController::class, 'index'])->middleware('permission:reorder-suggestions.view');
    Route::get('forecasts', [ForecastController::class, 'index'])
        ->middleware('permission:reorder-suggestions.view');
    Route::post('reorder-suggestions/create-draft-po', [ReorderSuggestionController::class, 'createDraftPo'])->middleware('permission:reorder-suggestions.create');

    Route::get('quotations', [QuotationController::class, 'index'])->middleware('permission:sales.view');
    Route::post('quotations', [QuotationController::class, 'store'])->middleware('permission:sales.create');
    Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->middleware('permission:sales.view');
    Route::put('quotations/{quotation}', [QuotationController::class, 'update'])->middleware('permission:sales.update');
    Route::delete('quotations/{quotation}', [QuotationController::class, 'destroy'])->middleware('permission:sales.delete');
    Route::post('quotations/{quotation}/convert', [QuotationController::class, 'convert'])->middleware('permission:pos.create');

    Route::prefix('support')->group(function () {
        Route::get('tickets', [SupportTicketController::class, 'index']);
        Route::post('tickets', [SupportTicketController::class, 'store']);
        Route::get('tickets/{id}', [SupportTicketController::class, 'show']);
        Route::get('tickets/{id}/replies', [SupportTicketController::class, 'replies']);
        Route::post('tickets/{id}/replies', [SupportTicketController::class, 'reply']);
        Route::put('tickets/{id}/close', [SupportTicketController::class, 'close']);
        Route::put('tickets/{id}/reopen', [SupportTicketController::class, 'reopen']);
    });
});
