<?php

namespace App\Reports\Stock;

use App\Models\StockMovement;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class StockMovementReport extends Report
{
    public string $key = 'stock-movements';
    public string $title = 'Stock movements';
    public string $group = 'Stock';
    public string $description = 'Every stock change in the period, with totals in and out per type.';
    public string $icon = 'arrow-left-right';

    public function filters(): array
    {
        return ['type' => ['Type', 'select', ['' => 'All types'] + collect(StockMovement::TYPES)->map(fn ($t) => $t[0])->all(), '']];
    }

    public function run(ReportContext $ctx): ReportResult
    {
        $base = DB::table('stock_movements')->whereBetween('stock_movements.created_at', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('stock_movements.branch_id', $ctx->branchId))
            ->when($ctx->param('type'), fn ($q, $t) => $q->where('stock_movements.type', $t));

        $byType = (clone $base)->groupBy('type')->selectRaw('type, COUNT(*) as n, SUM(quantity) as qty, SUM(quantity * unit_cost) as value')->get();

        $rows = (clone $base)->join('products', 'products.id', '=', 'stock_movements.product_id')
            ->join('branches', 'branches.id', '=', 'stock_movements.branch_id')
            ->leftJoin('users', 'users.id', '=', 'stock_movements.user_id')
            ->orderByDesc('stock_movements.id')->limit(5000)
            ->get(['stock_movements.created_at', 'products.name', 'branches.code', 'stock_movements.type', 'stock_movements.quantity', 'stock_movements.balance_after', 'stock_movements.unit_cost', 'stock_movements.reference_no', 'users.name as user'])
            ->map(fn ($r) => [
                'date' => $r->created_at, 'product' => $r->name, 'branch' => $r->code, 'type' => StockMovement::TYPES[$r->type][0] ?? $r->type,
                'qty' => (float) $r->quantity, 'balance' => (float) $r->balance_after, 'value' => round((float) $r->quantity * (float) $r->unit_cost, 2),
                'reference' => $r->reference_no, 'user' => $r->user ?? 'System',
            ]);

        $in = $byType->where('qty', '>', 0)->sum('qty');
        $out = abs($byType->where('qty', '<', 0)->sum('qty'));

        return new ReportResult(
            cards: [['Movements', number_format($byType->sum('n')), 'arrow-left-right', 'primary'], ['Units in', number_format($in, 0), 'trending-up', 'success'], ['Units out', number_format($out, 0), 'trending-down', 'danger']],
            columns: ['date' => ['Date', 'datetime'], 'product' => ['Product', 'text'], 'branch' => ['Branch', 'text'], 'type' => ['Type', 'text'], 'qty' => ['Change', 'qty'], 'balance' => ['Balance', 'qty'], 'value' => ['Value', 'money'], 'reference' => ['Reference', 'text'], 'user' => ['By', 'text']],
            rows: $rows,
            chart: $byType->isNotEmpty() ? [
                'chart' => ['type' => 'bar', 'height' => 260], 'series' => [['name' => 'Units', 'data' => $byType->map(fn ($t) => round((float) $t->qty, 2))->values()->all()]],
                'xaxis' => ['categories' => $byType->map(fn ($t) => StockMovement::TYPES[$t->type][0] ?? $t->type)->values()->all()],
                'plotOptions' => ['bar' => ['borderRadius' => 4, 'distributed' => true]], 'legend' => ['show' => false],
            ] : null,
            note: $rows->count() >= 5000 ? 'Showing the latest 5,000 movements. Narrow the dates or filter by type to see more.' : null,
        );
    }
}
