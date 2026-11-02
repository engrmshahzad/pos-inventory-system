<?php

namespace App\Reports\Profit;

use App\Models\Branch;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use App\Services\ProfitLossService;

class ProfitLossReport extends Report
{
    public string $key = 'profit-loss';
    public string $title = 'Profit & Loss';
    public string $group = 'Profit';
    public string $description = 'Net sales − cost of goods − expenses ± stock adjustments − transfer shortages = net profit. Per branch and consolidated.';
    public string $icon = 'trending-up';
    public bool $needsProfit = true;

    public function run(ReportContext $ctx): ReportResult
    {
        $service = app(ProfitLossService::class);

        // Consolidated view shows one column per branch plus the total.
        $branches = $ctx->branchId ? collect() : Branch::orderBy('name')->get(['id', 'code']);
        $results = $branches->mapWithKeys(fn ($b) => [$b->code => $service->compute($b->id, $ctx->from, $ctx->to)])->all();
        $results['total'] = $service->compute($ctx->branchId, $ctx->from, $ctx->to);

        $categories = collect($results)->flatMap(fn ($r) => array_keys($r['expenses']))->unique()->sort()->values();
        $line = fn (string $label, callable $value, string $style = '', string $type = 'money') => ['line' => $label, '_style' => $style, '_type' => $type]
            + collect($results)->map(fn ($r) => round($value($r), 2))->all();

        $rows = collect([
            $line('Gross sales (excl. tax & charges)', fn ($r) => $r['gross_sales']),
            $line('Less: sales returns', fn ($r) => -$r['returns'], 'indent'),
            $line('Net sales', fn ($r) => $r['net_sales'], 'subtotal'),
            $line('Less: cost of goods sold', fn ($r) => -$r['cogs'], 'indent'),
            $line('Gross profit', fn ($r) => $r['gross_profit'], 'subtotal'),
            $line('Gross margin %', fn ($r) => $r['gross_margin'], 'muted', 'percent'),
            ['line' => 'Expenses', '_style' => 'heading', '_type' => 'text'],
            ...$categories->map(fn ($cat) => $line($cat, fn ($r) => -($r['expenses'][$cat] ?? 0), 'indent'))->all(),
            $line('Total expenses', fn ($r) => -$r['total_expenses'], 'subtotal'),
            $line('Stock adjustments (net)', fn ($r) => $r['adjustments'], 'indent'),
            $line('Transfer shortages', fn ($r) => -$r['shortages'], 'indent'),
            $line('Net profit', fn ($r) => $r['net_profit'], 'total'),
            $line('Net margin %', fn ($r) => $r['net_margin'], 'muted', 'percent'),
        ]);

        $columns = ['line' => ['', 'text']];
        foreach ($branches as $b) {
            $columns[$b->code] = [$b->code, 'money'];
        }
        $columns['total'] = [$ctx->branchId ? 'Amount' : 'Total', 'money'];

        $t = $results['total'];

        return new ReportResult(
            cards: [
                ['Net sales', money($t['net_sales']), 'receipt', 'primary'],
                ['Gross profit', money($t['gross_profit']).' · '.round($t['gross_margin'], 1).'%', 'trending-up', 'success'],
                ['Expenses', money($t['total_expenses']), 'wallet', 'warning'],
                ['Net profit', money($t['net_profit']), $t['net_profit'] >= 0 ? 'check-circle' : 'alert-triangle', $t['net_profit'] >= 0 ? 'success' : 'danger'],
            ],
            columns: $columns,
            rows: $rows,
            chart: $branches->isNotEmpty() ? $this->moneyTooltipChart('bar', $branches->pluck('code')->all(), [
                ['name' => 'Net sales', 'data' => $branches->map(fn ($b) => $results[$b->code]['net_sales'])->all()],
                ['name' => 'Gross profit', 'data' => $branches->map(fn ($b) => $results[$b->code]['gross_profit'])->all()],
                ['name' => 'Net profit', 'data' => $branches->map(fn ($b) => $results[$b->code]['net_profit'])->all()],
            ], ['plotOptions' => ['bar' => ['borderRadius' => 5, 'columnWidth' => '55%']]]) : null,
            note: 'Returns exclude tax. Stock adjustments use cost at the time of the adjustment; transfer shortages are charged to the sending branch.',
            statement: true,
        );
    }
}
