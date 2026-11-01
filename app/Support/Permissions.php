<?php

namespace App\Support;

/**
 * Single source of truth for permissions and what each role gets.
 * The owner always receives every permission.
 */
class Permissions
{
    public const ALL = [
        'Dashboard' => ['dashboard.view'],
        'Branches' => ['branches.manage'],
        'Users' => ['users.view', 'users.create', 'users.edit', 'users.delete', 'roles.manage'],
        'Products' => ['products.view', 'products.create', 'products.edit', 'products.delete', 'products.import', 'categories.manage'],
        'Stock' => ['stock.view', 'stock.adjust', 'adjustments.approve'],
        'Transfers' => ['transfers.view', 'transfers.create', 'transfers.receive', 'transfers.approve'],
        'Sales' => ['pos.access', 'sales.view', 'sales.create', 'sales.return', 'sales.cancel', 'sales.edit_price', 'sales.discount'],
        'Customers' => ['customers.view', 'customers.create', 'customers.edit', 'customers.delete'],
        'Purchases' => ['purchases.view', 'purchases.create', 'purchases.edit', 'purchase_returns.create'],
        'Suppliers' => ['suppliers.view', 'suppliers.create', 'suppliers.edit', 'suppliers.delete'],
        'Finance' => ['payments.view', 'payments.create', 'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.delete'],
        'Reports' => ['reports.view', 'reports.profit'],
        'System' => ['settings.manage', 'activity.view', 'ui_kit.view'],
    ];

    public const MANAGER_EXCLUDED = [
        'branches.manage', 'roles.manage', 'adjustments.approve', 'transfers.approve',
        'settings.manage', 'activity.view', 'ui_kit.view', 'sales.cancel',
    ];

    public const CASHIER = [
        'dashboard.view', 'pos.access', 'sales.view', 'sales.create', 'sales.return',
        'customers.view', 'customers.create',
    ];

    /** @return list<string> */
    public static function flat(): array
    {
        return array_merge(...array_values(self::ALL));
    }

    /** @return list<string> */
    public static function forManager(): array
    {
        return array_values(array_diff(self::flat(), self::MANAGER_EXCLUDED));
    }
}
