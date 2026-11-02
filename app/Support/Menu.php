<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Sidebar menu, filtered by the user's permissions. Also feeds the Ctrl+K command palette.
 */
class Menu
{
    public static function definition(): array
    {
        return [
            'Main' => [
                ['label' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'layout-dashboard', 'can' => 'dashboard.view'],
            ],
            'Inventory' => [
                ['label' => 'Products', 'route' => 'products.index', 'icon' => 'package', 'can' => 'products.view', 'active' => ['products.*', 'barcodes.*']],
                ['label' => 'Categories', 'route' => 'categories.index', 'icon' => 'folder', 'can' => 'categories.manage'],
                ['label' => 'Brands', 'route' => 'brands.index', 'icon' => 'award', 'can' => 'categories.manage'],
                ['label' => 'Units', 'route' => 'units.index', 'icon' => 'ruler', 'can' => 'categories.manage'],
                ['label' => 'Stock', 'route' => 'stock.index', 'icon' => 'boxes', 'can' => 'stock.view', 'active' => ['stock.index']],
                ['label' => 'Stock History', 'route' => 'stock.movements', 'icon' => 'clock', 'can' => 'stock.view'],
                ['label' => 'Transfers', 'route' => 'transfers.index', 'icon' => 'arrow-left-right', 'can' => 'transfers.view', 'active' => ['transfers.*']],
                ['label' => 'Adjustments', 'route' => 'adjustments.index', 'icon' => 'sliders', 'can' => 'stock.adjust'],
                ['label' => 'Stock Count', 'route' => 'stock-counts.index', 'icon' => 'list', 'can' => 'stock.adjust'],
                ['label' => 'Expiry', 'route' => 'expiry.index', 'icon' => 'calendar', 'can' => 'stock.view'],
            ],
            'Sales' => [
                ['label' => 'POS', 'route' => 'pos.index', 'icon' => 'shopping-cart', 'can' => 'pos.access', 'kbd' => 'F2'],
                ['label' => 'Sales List', 'route' => 'sales.index', 'icon' => 'receipt', 'can' => 'sales.view'],
                ['label' => 'Cash Register', 'route' => 'register.index', 'icon' => 'wallet', 'can' => 'pos.access'],
                ['label' => 'Sale Returns', 'route' => 'sale-returns.index', 'icon' => 'undo', 'can' => 'sales.return'],
                ['label' => 'Customers', 'route' => 'customers.index', 'icon' => 'users', 'can' => 'customers.view'],
            ],
            'Purchases' => [
                ['label' => 'Purchases', 'route' => 'purchases.index', 'icon' => 'truck', 'can' => 'purchases.view'],
                ['label' => 'Purchase Returns', 'route' => 'purchase-returns.index', 'icon' => 'rotate-ccw', 'can' => 'purchase_returns.create'],
                ['label' => 'Suppliers', 'route' => 'suppliers.index', 'icon' => 'factory', 'can' => 'suppliers.view'],
            ],
            'Finance' => [
                ['label' => 'Payments', 'route' => 'payments.index', 'icon' => 'coins', 'can' => 'payments.view'],
                ['label' => 'Expenses', 'route' => 'expenses.index', 'icon' => 'banknote', 'can' => 'expenses.view'],
            ],
            'Reports' => [
                ['label' => 'Reports', 'route' => 'reports.index', 'icon' => 'bar-chart', 'can' => 'reports.view', 'active' => ['reports.*']],
                ['label' => 'Activity Log', 'route' => 'activity.index', 'icon' => 'clock', 'can' => 'activity.view'],
            ],
            'Settings' => [
                ['label' => 'Branches', 'route' => 'branches.index', 'icon' => 'building', 'can' => 'branches.manage'],
                ['label' => 'Users', 'route' => 'users.index', 'icon' => 'user-cog', 'can' => 'users.view', 'active' => ['users.*']],
                ['label' => 'Roles & Permissions', 'route' => 'roles.index', 'icon' => 'shield', 'can' => 'roles.manage'],
                ['label' => 'General Settings', 'route' => 'settings.index', 'icon' => 'settings', 'can' => 'settings.manage'],
                ['label' => 'UI Kit', 'route' => 'ui-kit', 'icon' => 'palette', 'can' => 'ui_kit.view'],
            ],
        ];
    }

    /** @return array<string, list<array>> groups with only the items the user may see */
    public static function for(User $user): array
    {
        $groups = [];

        foreach (static::definition() as $group => $items) {
            $visible = array_values(array_filter($items, fn ($item) => $user->can($item['can']) && Route::has($item['route'])));

            if ($visible) {
                $groups[$group] = array_map(fn ($item) => $item + [
                    'url' => route($item['route']),
                    'is_active' => request()->routeIs($item['active'] ?? [$item['route'], str_replace('.index', '.*', $item['route'])]),
                ], $visible);
            }
        }

        return $groups;
    }
}
