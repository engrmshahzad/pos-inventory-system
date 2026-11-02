<?php

namespace App\Reports\Money;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use App\Support\Sql;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaxReport extends Report
{
    public string $key = 'tax';
    public string $title = 'Tax report';
    public string $group = 'Money';
    public string $description = 'Tax collected on sales and paid on purchases, per month.';
    public string $icon = 'file-text';

    public function run(ReportContext $ctx): ReportResult
    {
        $sm = Sql::month('sold_at');
        $sales = DB::table('sales')->where('status', 'completed')->whereBetween('sold_at', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('branch_id', $ctx->branchId))
            ->groupBy(DB::raw($sm))->selectRaw("{$sm} as m, SUM(grand_total - tax_amount - shipping) as taxable, SUM(tax_amount) as tax")->get()->keyBy('m');

        $pm = Sql::month('purchase_date');
        $purchases = DB::table('purchases')->where('status', 'received')->whereBetween('purchase_date', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('branch_id', $ctx->branchId))
            ->groupBy(DB::raw($pm))->selectRaw("{$pm} as m, SUM(tax_amount) as tax")->pluck('tax', 'm');

        $rows = $sales->keys()->merge($purchases->keys())->unique()->sort()->values()->map(fn ($m) => [
            'month' => Carbon::createFromFormat('Y-m', $m)->format('M Y'),
            'taxable' => round((float) ($sales[$m]->taxable ?? 0), 2),
            'output' => round((float) ($sales[$m]->tax ?? 0), 2),
            'input' => round((float) ($purchases[$m] ?? 0), 2),
            'net' => round((float) ($sales[$m]->tax ?? 0) - (float) ($purchases[$m] ?? 0), 2),
        ]);

        return new ReportResult(
            cards: [['Tax collected (sales)', money($rows->sum('output')), 'receipt', 'primary'], ['Tax paid (purchases)', money($rows->sum('input')), 'truck', 'info'], ['Net', money($rows->sum('net')), 'wallet', 'warning']],
            columns: ['month' => ['Month', 'text'], 'taxable' => ['Taxable sales', 'money'], 'output' => ['Tax collected', 'money'], 'input' => ['Tax on purchases', 'money'], 'net' => ['Net', 'money']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['taxable', 'output', 'input', 'net']),
            note: settings('tax_enabled', '1') ? 'Mode: '.settings('tax_mode', 'exclusive').'. Tax rates come from each product.' : 'Tax is currently switched off in Settings.',
        );
    }
}
