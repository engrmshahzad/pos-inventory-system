<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\Catalogue\BarcodeController;
use App\Http\Controllers\Catalogue\BrandController;
use App\Http\Controllers\Catalogue\CategoryController;
use App\Http\Controllers\Catalogue\ProductController;
use App\Http\Controllers\Catalogue\ProductImportController;
use App\Http\Controllers\Catalogue\UnitController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
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

    // Modules of later phases show a "Coming soon" page until they are built.
    $comingSoon = [
        // uri, route name, permission, label, phase, icon
        ['pos', 'pos.index', 'pos.access', 'POS', 3, 'shopping-cart'],
        ['sales', 'sales.index', 'sales.view', 'Sales', 3, 'receipt'],
        ['customers', 'customers.index', 'customers.view', 'Customers', 4, 'users'],
        ['sale-returns', 'sale-returns.index', 'sales.return', 'Sale Returns', 4, 'undo'],
        ['purchases', 'purchases.index', 'purchases.view', 'Purchases', 4, 'truck'],
        ['purchase-returns', 'purchase-returns.index', 'purchase_returns.create', 'Purchase Returns', 4, 'rotate-ccw'],
        ['suppliers', 'suppliers.index', 'suppliers.view', 'Suppliers', 4, 'factory'],
        ['payments', 'payments.index', 'payments.view', 'Payments', 4, 'wallet'],
        ['transfers', 'transfers.index', 'transfers.view', 'Stock Transfers', 5, 'arrow-left-right'],
        ['adjustments', 'adjustments.index', 'stock.adjust', 'Stock Adjustments', 5, 'sliders'],
        ['expenses', 'expenses.index', 'expenses.view', 'Expenses', 6, 'banknote'],
        ['reports', 'reports.index', 'reports.view', 'Reports', 6, 'bar-chart'],
        ['settings', 'settings.index', 'settings.manage', 'General Settings', 6, 'settings'],
    ];

    foreach ($comingSoon as [$uri, $name, $permission, $label, $phase, $icon]) {
        Route::get($uri, ComingSoonController::class)
            ->middleware("permission:{$permission}")
            ->defaults('module', $label)
            ->defaults('phase', $phase)
            ->defaults('icon', $icon)
            ->name($name);
    }
});

require __DIR__.'/auth.php';
