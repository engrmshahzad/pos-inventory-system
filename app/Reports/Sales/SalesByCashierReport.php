<?php

namespace App\Reports\Sales;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class SalesByCashierReport extends Report
{
    public string $key = 'sales-by-cashier';
    public string $title = 'Sales by cashier';
    public string $group = 'Sales';
    public string $description = 'Invoices, totals, average bill, discounts and credit given per staff member.';
    public string $icon = 'user';

    public function run(ReportContext $ctx): ReportResult
    {
        $cost = DB::table('sale_items')->selectRaw('sale_id, SUM(purchase_price * quantity) as cost')->groupBy('sale_id');
        $rows = $this->sales($ctx)->join('users', 'users.id', '=', 'sales.user_id')->leftJoinSub($cost, 'c', 'c.sale_id', '=', 'sales.id')
            ->groupBy('users.id', 'users.name')
            ->selectRaw('users.name, COUNT(*) as invoices, SUM(sales.grand_total) as total, SUM(sales.discount_amount) as discount, SUM(sales.due_amount) as credit,
                SUM(sales.grand_total - sales.tax_amount - sales.shipping - COALESCE(c.cost, 0)) as profit')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => array_filter([
                'name' => $r->name, 'invoices' => (int) $r->invoices, 'total' => round((float) $r->total, 2),
                'average' => round((float) $r->total / max((int) $r->invoices, 1), 2), 'discount' => round((float) $r->discount, 2),
                'credit' => round((float) $r->credit, 2), 'profit' => $ctx->canProfit ? round((float) $r->profit, 2) : null,
            ], fn ($v) => $v !== null));

        $columns = ['name' => ['Staff', 'text'], 'invoices' => ['Invoices', 'number'], 'total' => ['Sales', 'money'], 'average' => ['Avg bill', 'money'], 'discount' => ['Discounts', 'money'], 'credit' => ['Credit given', 'money']];
        if ($ctx->canProfit) {
            $columns['profit'] = ['Gross profit', 'money'];
        }

        return new ReportResult(
            cards: [['Staff selling', (string) $rows->count(), 'users', 'primary'], ['Total sales', money($rows->sum('total')), 'receipt', 'success'], ['Discounts given', money($rows->sum('discount')), 'sliders', 'warning']],
            columns: $columns,
            rows: $rows,
            totals: ReportResult::sum($rows, array_values(array_intersect(['invoices', 'total', 'discount', 'credit', 'profit'], array_keys($columns)))),
            chart: $rows->isNotEmpty() ? $this->moneyTooltipChart('bar', $rows->pluck('name')->all(), [['name' => 'Sales', 'data' => $rows->pluck('total')->all()]], ['plotOptions' => ['bar' => ['borderRadius' => 6, 'columnWidth' => '45%']]]) : null,
        );
    }
}
