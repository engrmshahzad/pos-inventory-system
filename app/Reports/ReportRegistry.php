<?php

namespace App\Reports;

use App\Models\User;
use Illuminate\Support\Collection;

class ReportRegistry
{
    public const REPORTS = [
        Sales\SalesReport::class,
        Sales\SalesByProductReport::class,
        Sales\SalesByCategoryReport::class,
        Sales\SalesByCashierReport::class,
        Sales\SalesByCustomerReport::class,
        Sales\SalesByPaymentMethodReport::class,
        Profit\ProfitLossReport::class,
        Profit\BranchComparisonReport::class,
        Purchases\PurchasesBySupplierReport::class,
        Purchases\PurchasesByProductReport::class,
        Stock\StockReport::class,
        Stock\StockValuationReport::class,
        Stock\LowStockReport::class,
        Stock\DeadStockReport::class,
        Stock\StockMovementReport::class,
        Stock\TransferReport::class,
        Stock\AdjustmentReport::class,
        Money\CustomerAgingReport::class,
        Money\SupplierAgingReport::class,
        Money\ExpenseReport::class,
        Money\TaxReport::class,
    ];

    /** @return Collection<string, Report> */
    public static function all(): Collection
    {
        return collect(self::REPORTS)->map(fn ($class) => new $class)->keyBy('key');
    }

    public static function find(string $key): ?Report
    {
        return self::all()->get($key);
    }

    public static function forUser(User $user): Collection
    {
        return self::all()->filter(fn (Report $r) => $r->allowedFor($user));
    }
}
