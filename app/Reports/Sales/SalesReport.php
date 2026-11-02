<?php

namespace App\Reports\Sales;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use App\Support\Sql;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesReport extends Report
{
    public string $key = 'sales';
    public string $title = 'Sales report';
    public string $group = 'Sales';
    public string $description = 'Totals per day or month: invoices, discounts, tax, returns, paid and due.';
    public string $icon = 'receipt';

    public function filters(): array
    {
        return ['group' => ['Group by', 'select', ['daily' => 'Day', 'monthly' => 'Month'], 'daily']];
    }

    public function run(ReportContext $ctx): ReportResult
    {
        $monthly = $ctx->param('group') === 'monthly';
        $period = $monthly ? Sql::month('sales.sold_at') : Sql::day('sales.sold_at');
        $cost = DB::table('sale_items')->selectRaw('sale_id, SUM(purchase_price * quantity) as cost')->groupBy('sale_id');

        $rows = $this->sales($ctx)->leftJoinSub($cost, 'c', 'c.sale_id', '=', 'sales.id')
            ->groupBy(DB::raw($period))
            ->selectRaw("{$period} as period, COUNT(*) as invoices, SUM(subtotal) as subtotal, SUM(discount_amount) as discount,
                SUM(tax_amount) as tax, SUM(shipping) as charges, SUM(grand_total) as total, SUM(paid_amount) as paid, SUM(due_amount) as due,
                SUM(grand_total - tax_amount - shipping - COALESCE(c.cost, 0)) as profit")
            ->orderBy('period')->get()->keyBy('period');

        $returnPeriod = $monthly ? Sql::month('returned_at') : Sql::day('returned_at');
        $returns = DB::table('sale_returns')->whereBetween('returned_at', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('branch_id', $ctx->branchId))
            ->groupBy(DB::raw($returnPeriod))->selectRaw("{$returnPeriod} as period, SUM(total) as total")->pluck('total', 'period');

        $periods = $rows->keys()->merge($returns->keys())->unique()->sort()->values();
        $data = $periods->map(function ($p) use ($rows, $returns, $monthly, $ctx) {
            $r = $rows[$p] ?? null;
            $ret = (float) ($returns[$p] ?? 0);
            $row = [
                'period' => $monthly ? Carbon::createFromFormat('Y-m', $p)->format('M Y') : fmtDate($p),
                'invoices' => (int) ($r->invoices ?? 0),
                'subtotal' => (float) ($r->subtotal ?? 0),
                'discount' => (float) ($r->discount ?? 0),
                'tax' => (float) ($r->tax ?? 0),
                'total' => (float) ($r->total ?? 0),
                'returns' => $ret,
                'net' => round((float) ($r->total ?? 0) - $ret, 2),
                'paid' => (float) ($r->paid ?? 0),
                'due' => (float) ($r->due ?? 0),
            ];
            if ($ctx->canProfit) {
                $row['profit'] = round((float) ($r->profit ?? 0), 2);
            }

            return $row;
        });

        $columns = [
            'period' => [$monthly ? 'Month' : 'Date', 'text'], 'invoices' => ['Invoices', 'number'], 'subtotal' => ['Subtotal', 'money'],
            'discount' => ['Discount', 'money'], 'tax' => ['Tax', 'money'], 'total' => ['Sales', 'money'], 'returns' => ['Returns', 'money'],
            'net' => ['Net sales', 'money'], 'paid' => ['Paid', 'money'], 'due' => ['Due', 'money'],
        ];
        if ($ctx->canProfit) {
            $columns['profit'] = ['Gross profit', 'money'];
        }

        $total = $data->sum('total');
        $invoices = $data->sum('invoices');
        $cards = [
            ['Total sales', money($total), 'receipt', 'primary'],
            ['Invoices', number_format($invoices), 'file-text', 'info'],
            ['Average bill', money($invoices ? $total / $invoices : 0), 'banknote', 'success'],
            ['Returns', money($data->sum('returns')), 'undo', 'warning'],
        ];
        if ($ctx->canProfit) {
            $cards[] = ['Gross profit', money($data->sum('profit')), 'trending-up', 'success'];
        }

        $series = [['name' => 'Sales', 'data' => $data->pluck('total')->all()]];
        if ($ctx->canProfit) {
            $series[] = ['name' => 'Gross profit', 'data' => $data->pluck('profit')->all()];
        }

        return new ReportResult(
            cards: $cards,
            columns: $columns,
            rows: $data,
            totals: ReportResult::sum($data, array_diff(array_keys($columns), ['period'])),
            chart: $data->count() > 1 ? $this->moneyTooltipChart('area', $data->pluck('period')->all(), $series, ['fill' => ['type' => 'gradient', 'gradient' => ['opacityFrom' => .35, 'opacityTo' => 0]]]) : null,
        );
    }
}
