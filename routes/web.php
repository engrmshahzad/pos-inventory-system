<?php

use App\Http\Controllers\BranchController;
use App\Http\Controllers\BranchSwitchController;
use App\Http\Controllers\ComingSoonController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->middleware('permission:dashboard.view')->name('dashboard');
    Route::post('/branch/switch', BranchSwitchController::class)->middleware('role:owner')->name('branch.switch');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

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
        ['products', 'products.index', 'products.view', 'Products', 2, 'package'],
        ['categories', 'categories.index', 'categories.manage', 'Categories', 2, 'folder'],
        ['brands', 'brands.index', 'categories.manage', 'Brands', 2, 'award'],
        ['units', 'units.index', 'categories.manage', 'Units', 2, 'ruler'],
        ['stock', 'stock.index', 'stock.view', 'Stock', 2, 'boxes'],
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
