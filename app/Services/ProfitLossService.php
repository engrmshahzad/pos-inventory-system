<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Profit & Loss for a period and branch (null = all branches).
 *
 *   Gross sales      = Σ sales (grand total − tax − other charges)
 * − Sales returns    = Σ returns, tax portion removed
 * = Net sales
 * − Cost of goods    = Σ cost snapshot of items sold − cost of items returned
 * = Gross profit
 * − Expenses         (by category)
 * ± Stock adjustments (approved; losses negative, found/corrections positive)
 * − Transfer shortages (lost in transit, charged to the sending branch)
 * = Net profit
 */
class ProfitLossService
{
    public function compute(?int $branchId, Carbon $from, Carbon $to): array
    {
        [$start, $end] = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $sales = DB::table('sales')->where('status', 'completed')->whereBetween('sold_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId));

        $grossSales = (float) (clone $sales)->sum(DB::raw('grand_total - tax_amount - shipping'));
        $cogs = (float) DB::table('sale_items')->whereIn('sale_id', (clone $sales)->select('id'))->sum(DB::raw('purchase_price * quantity'));

        $returnLines = DB::table('sale_return_items')
            ->join('sale_returns', 'sale_returns.id', '=', 'sale_return_items.sale_return_id')
            ->join('sale_items', 'sale_items.id', '=', 'sale_return_items.sale_item_id')
            ->whereBetween('sale_returns.returned_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('sale_returns.branch_id', $branchId));

        // Refunds include the tax share of the line; remove it so returns compare with sales excl. tax.
        $returns = (float) (clone $returnLines)->sum(DB::raw('sale_return_items.total * CASE WHEN sale_items.line_total > 0 THEN (sale_items.line_total - sale_items.tax_amount) / sale_items.line_total ELSE 1 END'));
        $returnedCost = (float) (clone $returnLines)->sum(DB::raw('sale_return_items.quantity * sale_items.purchase_price'));

        $expenses = DB::table('expenses')->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereNull('expenses.deleted_at')
            ->whereBetween('expenses.expense_date', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('expenses.branch_id', $branchId))
            ->groupBy('expense_categories.name')->orderBy('expense_categories.name')
            ->selectRaw('expense_categories.name as name, SUM(expenses.amount) as total')
            ->pluck('total', 'name')
            ->map(fn ($v) => round((float) $v, 2))->all();

        $adjustments = (float) DB::table('stock_adjustments')->where('status', 'approved')
            ->whereBetween('adjustment_date', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('total_value');

        $shortages = (float) DB::table('stock_transfer_items')->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->where('stock_transfers.status', 'partially_received')->whereBetween('stock_transfers.received_at', [$start, $end])
            ->when($branchId, fn ($q) => $q->where('stock_transfers.from_branch_id', $branchId))
            ->sum(DB::raw('stock_transfer_items.shortage * stock_transfer_items.unit_cost'));

        $netSales = $grossSales - $returns;
        $netCogs = $cogs - $returnedCost;
        $grossProfit = $netSales - $netCogs;
        $totalExpenses = array_sum($expenses);
        $netProfit = $grossProfit - $totalExpenses + $adjustments - $shortages;

        return array_map(fn ($v) => is_array($v) ? $v : round($v, 2), [
            'gross_sales' => $grossSales,
            'returns' => $returns,
            'net_sales' => $netSales,
            'cogs' => $netCogs,
            'gross_profit' => $grossProfit,
            'gross_margin' => $netSales != 0 ? $grossProfit / $netSales * 100 : 0,
            'expenses' => $expenses,
            'total_expenses' => $totalExpenses,
            'adjustments' => $adjustments,
            'shortages' => $shortages,
            'net_profit' => $netProfit,
            'net_margin' => $netSales != 0 ? $netProfit / $netSales * 100 : 0,
        ]);
    }
}
