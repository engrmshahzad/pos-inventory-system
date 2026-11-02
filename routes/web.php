<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\Catalogue\BarcodeController;
use App\Http\Controllers\Catalogue\BrandController;
use App\Http\Controllers\Catalogue\CategoryController;
use App\Http\Controllers\Catalogue\ProductController;
use App\Http\Controllers\Catalogue\ProductImportController;
use App\Http\Controllers\Catalogue\UnitController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Finance\ExpenseCategoryController;
use App\Http\Controllers\Finance\ExpenseController;
use App\Http\Controllers\Finance\PaymentController;
use App\Http\Controllers\Inventory\AdjustmentController;
use App\Http\Controllers\Inventory\ExpiryController;
use App\Http\Controllers\Inventory\StockCountController;
use App\Http\Controllers\Inventory\TransferController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Parties\CustomerController;
use App\Http\Controllers\Parties\SupplierController;
use App\Http\Controllers\Purchases\PurchaseController;
use App\Http\Controllers\Purchases\PurchaseReturnController;
use App\Http\Controllers\Sales\SaleReturnController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\Sales\CashRegisterController;
use App\Http\Controllers\Sales\PosController;
use App\Http\Controllers\Sales\SaleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::post('/dashboard/refresh', [DashboardController::class, 'refresh'])->middleware('permission:dashboard.view')->name('dashboard.refresh');
    Route::post('/branch/switch', BranchSwitchController::class)->middleware('role:owner')->name('branch.switch');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    // Inventory: catalogue
    Route::middleware('permission:categories.manage')->group(function () {
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('brands', BrandController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('units', UnitController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::prefix('products')->group(function () {
        Route::get('search', [ProductController::class, 'search'])->middleware('permission:products.view|pos.access')->name('products.search');
        Route::get('export', [ProductController::class, 'export'])->middleware('permission:products.view')->name('products.export');
        Route::post('bulk', [ProductController::class, 'bulk'])->middleware('permission:products.view')->name('products.bulk');
        Route::get('barcodes', [BarcodeController::class, 'index'])->middleware('permission:products.view')->name('barcodes.index');
        Route::get('barcodes/print', [BarcodeController::class, 'print'])->middleware('permission:products.view')->name('barcodes.print');

        Route::middleware('permission:products.create')->group(function () {
            Route::get('import', [ProductImportController::class, 'create'])->name('products.import');
            Route::get('import/template', [ProductImportController::class, 'template'])->name('products.import.template');
            Route::post('import', [ProductImportController::class, 'store'])->name('products.import.store');
        });
    });
    Route::get('products', [ProductController::class, 'index'])->middleware('permission:products.view')->name('products.index');
    Route::get('products/create', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('products', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');
    Route::get('products/{product}', [ProductController::class, 'show'])->middleware('permission:products.view')->name('products.show');
    Route::get('products/{product}/edit', [ProductController::class, 'edit'])->middleware('permission:products.edit')->name('products.edit');
    Route::put('products/{product}', [ProductController::class, 'update'])->middleware('permission:products.edit')->name('products.update');
    Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('permission:products.delete')->name('products.destroy');

    // Inventory: stock
    Route::middleware('permission:stock.view')->group(function () {
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::get('stock/movements', [StockController::class, 'movements'])->name('stock.movements');
    });

    // Inventory: transfers
    Route::get('transfers/suggestions', [TransferController::class, 'suggestions'])->middleware(['role:owner', 'permission:transfers.view'])->name('transfers.suggestions');
    Route::get('transfers/create', [TransferController::class, 'create'])->middleware('permission:transfers.create')->name('transfers.create');
    Route::post('transfers', [TransferController::class, 'store'])->middleware('permission:transfers.create')->name('transfers.store');
    Route::middleware('permission:transfers.view')->group(function () {
        Route::get('transfers', [TransferController::class, 'index'])->name('transfers.index');
        Route::get('transfers/{transfer}', [TransferController::class, 'show'])->name('transfers.show');
        Route::get('transfers/{transfer}/challan', [TransferController::class, 'challan'])->name('transfers.challan');
        Route::post('transfers/{transfer}/send', [TransferController::class, 'send'])->name('transfers.send');
        Route::post('transfers/{transfer}/receive', [TransferController::class, 'receive'])->name('transfers.receive');
        Route::post('transfers/{transfer}/cancel', [TransferController::class, 'cancel'])->name('transfers.cancel');
    });

    // Inventory: adjustments, stock counts, expiry
    Route::middleware('permission:stock.adjust')->group(function () {
        Route::get('adjustments', [AdjustmentController::class, 'index'])->name('adjustments.index');
        Route::get('adjustments/create', [AdjustmentController::class, 'create'])->name('adjustments.create');
        Route::post('adjustments', [AdjustmentController::class, 'store'])->name('adjustments.store');
        Route::get('adjustments/{adjustment}', [AdjustmentController::class, 'show'])->name('adjustments.show');

        Route::get('stock-counts', [StockCountController::class, 'index'])->name('stock-counts.index');
        Route::post('stock-counts', [StockCountController::class, 'store'])->name('stock-counts.store');
        Route::get('stock-counts/{stockCount}', [StockCountController::class, 'show'])->name('stock-counts.show');
        Route::put('stock-counts/{stockCount}', [StockCountController::class, 'update'])->name('stock-counts.update');
        Route::post('stock-counts/{stockCount}/cancel', [StockCountController::class, 'cancel'])->name('stock-counts.cancel');
        Route::get('stock-counts/{stockCount}/sheet', [StockCountController::class, 'sheet'])->name('stock-counts.sheet');
    });
    Route::middleware('permission:adjustments.approve')->group(function () {
        Route::post('adjustments/{adjustment}/approve', [AdjustmentController::class, 'approve'])->name('adjustments.approve');
        Route::post('adjustments/{adjustment}/reject', [AdjustmentController::class, 'reject'])->name('adjustments.reject');
    });
    Route::get('expiry', [ExpiryController::class, 'index'])->middleware('permission:stock.view')->name('expiry.index');

    Route::get('notifications/{id}', [NotificationController::class, 'open'])->name('notifications.open');
    Route::post('notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    // Sales: POS (full-screen) and its JSON endpoints
    Route::middleware('permission:pos.access')->prefix('pos')->name('pos.')->group(function () {
        Route::get('/', [PosController::class, 'index'])->name('index');
        Route::get('products', [PosController::class, 'products'])->name('products');
        Route::get('lookup', [PosController::class, 'lookup'])->name('lookup');
        Route::post('checkout', [PosController::class, 'checkout'])->name('checkout');
        Route::post('hold', [PosController::class, 'hold'])->name('hold');
        Route::get('held', [PosController::class, 'held'])->name('held');
        Route::get('held/{sale}', [PosController::class, 'resume'])->name('held.resume');
        Route::delete('held/{sale}', [PosController::class, 'destroyHeld'])->name('held.destroy');
        Route::get('customers', [PosController::class, 'customers'])->name('customers');
        Route::post('customers', [PosController::class, 'storeCustomer'])->name('customers.store');
    });

    Route::middleware('permission:pos.access')->prefix('cash-register')->name('register.')->group(function () {
        Route::get('/', [CashRegisterController::class, 'index'])->name('index');
        Route::post('open', [CashRegisterController::class, 'open'])->name('open');
        Route::post('{register}/close', [CashRegisterController::class, 'close'])->name('close');
        Route::get('{register}/report', [CashRegisterController::class, 'report'])->name('report');
    });

    Route::middleware('permission:sales.view')->group(function () {
        Route::get('sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
        Route::get('sales/{sale}/pdf', [SaleController::class, 'pdf'])->name('sales.pdf');
        Route::post('sales/{sale}/payments', [SaleController::class, 'receivePayment'])->name('sales.payments.store');
    });

    // Sale returns
    Route::middleware('permission:sales.return')->group(function () {
        Route::get('sale-returns', [SaleReturnController::class, 'index'])->name('sale-returns.index');
        Route::get('sale-returns/create', [SaleReturnController::class, 'create'])->name('sale-returns.create');
        Route::post('sale-returns', [SaleReturnController::class, 'store'])->name('sale-returns.store');
        Route::get('sale-returns/{saleReturn}', [SaleReturnController::class, 'show'])->name('sale-returns.show');
        Route::get('sale-returns/{saleReturn}/receipt', [SaleReturnController::class, 'receipt'])->name('sale-returns.receipt');
    });

    // Customers (khata)
    Route::get('customers/search', [CustomerController::class, 'search'])->middleware('permission:customers.view|payments.create')->name('customers.search');
    Route::get('customers/{customer}/invoices', [CustomerController::class, 'openInvoices'])->middleware('permission:payments.create')->name('customers.invoices');
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::put('customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::get('customers/{customer}/statement', [CustomerController::class, 'statement'])->name('customers.statement');
    });

    // Suppliers
    Route::get('suppliers/search', [SupplierController::class, 'search'])->middleware('permission:suppliers.view|purchases.create|payments.create')->name('suppliers.search');
    Route::get('suppliers/{supplier}/invoices', [SupplierController::class, 'openInvoices'])->middleware('permission:payments.create')->name('suppliers.invoices');
    Route::middleware('permission:suppliers.view')->group(function () {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
        Route::get('suppliers/{supplier}/statement', [SupplierController::class, 'statement'])->name('suppliers.statement');
    });

    // Purchases
    Route::get('purchases/create', [PurchaseController::class, 'create'])->middleware('permission:purchases.create')->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])->middleware('permission:purchases.create')->name('purchases.store');
    Route::middleware('permission:purchases.view')->group(function () {
        Route::get('purchases', [PurchaseController::class, 'index'])->name('purchases.index');
        Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])->name('purchases.show');
        Route::get('purchases/{purchase}/pdf', [PurchaseController::class, 'pdf'])->name('purchases.pdf');
        Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
        Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])->name('purchases.destroy');
    });

    Route::middleware('permission:purchase_returns.create')->group(function () {
        Route::get('purchase-returns', [PurchaseReturnController::class, 'index'])->name('purchase-returns.index');
        Route::get('purchase-returns/create', [PurchaseReturnController::class, 'create'])->name('purchase-returns.create');
        Route::post('purchase-returns', [PurchaseReturnController::class, 'store'])->name('purchase-returns.store');
        Route::get('purchase-returns/{purchaseReturn}', [PurchaseReturnController::class, 'show'])->name('purchase-returns.show');
    });

    // Payments
    Route::get('payments/create', [PaymentController::class, 'create'])->middleware('permission:payments.create')->name('payments.create');
    Route::post('payments', [PaymentController::class, 'store'])->middleware('permission:payments.create')->name('payments.store');
    Route::middleware('permission:payments.view')->group(function () {
        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
        Route::get('payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    });

    // Expenses
    Route::middleware('permission:expenses.view')->group(function () {
        Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
        Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
        Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });
    Route::middleware('permission:expenses.edit')->group(function () {
        Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
        Route::put('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');
        Route::delete('expense-categories/{expenseCategory}', [ExpenseCategoryController::class, 'destroy'])->name('expense-categories.destroy');
    });

    // Reports
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/{key}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{key}/export/{format}', [ReportController::class, 'export'])->whereIn('format', ['xlsx', 'pdf', 'print'])->name('reports.export');
    });
    Route::get('activity-log', [ActivityLogController::class, 'index'])->middleware(['role:owner', 'permission:activity.view'])->name('activity.index');

    // Settings & backups (owner)
    Route::middleware(['role:owner', 'permission:settings.manage'])->group(function () {
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.index');
        Route::put('settings/{group}', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/backups', [BackupController::class, 'store'])->name('backups.store');
        Route::get('settings/backups/{file}', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('settings/backups/{file}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });

    // Settings
    Route::middleware('permission:branches.manage')->group(function () {
        Route::resource('branches', BranchController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/branches/{branch}/toggle', [BranchController::class, 'toggle'])->name('branches.toggle');
    });

    Route::middleware('permission:users.view')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::patch('/users/{user}/toggle', [UserController::class, 'toggle'])->name('users.toggle');
    });
    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.manage')->name('roles.index');

    Route::view('/ui-kit', 'ui-kit')->middleware(['role:owner', 'permission:ui_kit.view'])->name('ui-kit');

});

require __DIR__.'/auth.php';
