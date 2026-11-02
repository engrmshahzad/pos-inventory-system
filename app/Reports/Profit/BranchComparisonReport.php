<?php

namespace App\Reports\Profit;

use App\Models\Branch;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use App\Services\ProfitLossService;
use Illuminate\Support\Facades\DB;

class BranchComparisonReport extends Report
{
    public string $key = 'branch-comparison';
    public string $title = 'Branch comparison';
    public string $group = 'Profit';
    public string $description = 'Sales, profit, expenses, receivables and stock side by side for every branch.';
    public string $icon = 'building';
    public bool $ownerOnly = true;

    public function run(ReportContext $ctx): ReportResult
    {
        $pl = app(ProfitLossService::class);

        $rows = Branch::orderBy('name')->get()->map(function (Branch $b) use ($ctx, $pl) {
            $sales = DB::table('sales')->where('status', 'completed')->where('branch_id', $b->id)->whereBetween('sold_at', [$ctx->start(), $ctx->end()]);
            $invoices = (clone $sales)->count();
            $total = (float) (clone $sales)->sum('grand_total');
            $p = $pl->compute($b->id, $ctx->from, $ctx->to);

            return array_filter([
                'branch' => $b->name, 'invoices' => $invoices, 'sales' => round($total, 2), 'average' => round($total / max($invoices, 1), 2),
                'gross_profit' => $ctx->canProfit ? $p['gross_profit'] : null,
                'expenses' => $p['total_expenses'],
                'net_profit' => $ctx->canProfit ? $p['net_profit'] : null,
                'receivable' => round((float) DB::table('sales')->where('status', 'completed')->where('branch_id', $b->id)->sum('due_amount'), 2),
                'stock_value' => round((float) DB::table('branch_stock')->join('products', 'products.id', '=', 'branch_stock.product_id')
                    ->whereNull('products.deleted_at')->where('branch_stock.branch_id', $b->id)->where('branch_stock.quantity', '>', 0)
                    ->sum(DB::raw('branch_stock.quantity * products.purchase_price')), 2),
            ], fn ($v) => $v !== null);
        });

        $columns = ['branch' => ['Branch', 'text'], 'invoices' => ['Invoices', 'number'], 'sales' => ['Sales', 'money'], 'average' => ['Avg bill', 'money']];
        if ($ctx->canProfit) {
            $columns['gross_profit'] = ['Gross profit', 'money'];
        }
        $columns['expenses'] = ['Expenses', 'money'];
        if ($ctx->canProfit) {
            $columns['net_profit'] = ['Net profit', 'money'];
        }
        $columns += ['receivable' => ['Receivable now', 'money'], 'stock_value' => ['Stock value now', 'money']];

        $best = $rows->sortByDesc('sales')->first();
        $series = [['name' => 'Sales', 'data' => $rows->pluck('sales')->all()]];
        if ($ctx->canProfit) {
            $series[] = ['name' => 'Net profit', 'data' => $rows->pluck('net_profit')->all()];
        }

        return new ReportResult(
            cards: [['Branches', (string) $rows->count(), 'building', 'primary'], ['Top branch by sales', $best['branch'] ?? '—', 'award', 'success'], ['All sales', money($rows->sum('sales')), 'receipt', 'info']],
            columns: $columns,
            rows: $rows,
            totals: ReportResult::sum($rows, array_values(array_diff(array_keys($columns), ['branch', 'average']))),
            chart: $this->moneyTooltipChart('bar', $rows->pluck('branch')->all(), $series, ['plotOptions' => ['bar' => ['borderRadius' => 6, 'columnWidth' => '45%']]]),
        );
    }
}
