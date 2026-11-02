<?php

namespace App\Reports\Stock;

use App\Models\StockAdjustment;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class AdjustmentReport extends Report
{
    public string $key = 'adjustments';
    public string $title = 'Adjustment report';
    public string $group = 'Stock';
    public string $description = 'Stock written off or added, by reason, with value impact.';
    public string $icon = 'sliders';

    public function filters(): array
    {
        return [
            'reason' => ['Reason', 'select', ['' => 'All reasons'] + StockAdjustment::REASONS, ''],
            'status' => ['Status', 'select', ['' => 'Any status'] + collect(StockAdjustment::STATUSES)->map(fn ($s) => $s[0])->all(), 'approved'],
        ];
    }

    public function run(ReportContext $ctx): ReportResult
    {
        $base = DB::table('stock_adjustments as a')->join('branches as b', 'b.id', '=', 'a.branch_id')->join('users as u', 'u.id', '=', 'a.created_by')
            ->whereBetween('a.adjustment_date', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('a.branch_id', $ctx->branchId))
            ->when($ctx->param('reason'), fn ($q, $r) => $q->where('a.reason', $r))
            ->when($ctx->param('status'), fn ($q, $s) => $q->where('a.status', $s));

        $rows = (clone $base)->orderByDesc('a.adjustment_date')
            ->get(['a.id', 'a.adjustment_no', 'a.adjustment_date', 'b.code', 'a.reason', 'a.status', 'a.total_value', 'u.name'])
            ->map(fn ($r) => [
                'number' => $r->adjustment_no, 'date' => $r->adjustment_date, 'branch' => $r->code, 'reason' => StockAdjustment::REASONS[$r->reason] ?? $r->reason,
                'status' => StockAdjustment::STATUSES[$r->status][0] ?? $r->status, 'value' => (float) $r->total_value, 'by' => $r->name, '_url' => route('adjustments.show', $r->id),
            ]);

        $byReason = $rows->groupBy('reason')->map(fn ($g) => round(abs($g->sum('value')), 2));

        return new ReportResult(
            cards: [['Adjustments', (string) $rows->count(), 'sliders', 'primary'], ['Losses', money(abs($rows->where('value', '<', 0)->sum('value'))), 'trending-down', 'danger'], ['Gains', money($rows->where('value', '>', 0)->sum('value')), 'trending-up', 'success']],
            columns: ['number' => ['Adjustment', 'text'], 'date' => ['Date', 'date'], 'branch' => ['Branch', 'text'], 'reason' => ['Reason', 'text'], 'status' => ['Status', 'badge'], 'value' => ['Value impact', 'money'], 'by' => ['By', 'text']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['value']),
            chart: $byReason->isNotEmpty() ? $this->donut($byReason->keys()->all(), $byReason->values()->all()) : null,
        );
    }
}
