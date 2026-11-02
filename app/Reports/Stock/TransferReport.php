<?php

namespace App\Reports\Stock;

use App\Models\StockTransfer;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class TransferReport extends Report
{
    public string $key = 'transfers';
    public string $title = 'Transfer report';
    public string $group = 'Stock';
    public string $description = 'Transfers between branches with quantities, value and shortages.';
    public string $icon = 'truck';

    public function filters(): array
    {
        return ['status' => ['Status', 'select', ['' => 'Any status'] + collect(StockTransfer::STATUSES)->map(fn ($s) => $s[0])->all(), '']];
    }

    public function run(ReportContext $ctx): ReportResult
    {
        $rows = DB::table('stock_transfers as t')
            ->join('branches as f', 'f.id', '=', 't.from_branch_id')->join('branches as d', 'd.id', '=', 't.to_branch_id')
            ->leftJoin('stock_transfer_items as i', 'i.stock_transfer_id', '=', 't.id')
            ->whereBetween('t.transfer_date', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where(fn ($q) => $q->where('t.from_branch_id', $ctx->branchId)->orWhere('t.to_branch_id', $ctx->branchId)))
            ->when($ctx->param('status'), fn ($q, $s) => $q->where('t.status', $s))
            ->groupBy('t.id', 't.transfer_no', 't.transfer_date', 'f.code', 'd.code', 't.status')
            ->selectRaw('t.id, t.transfer_no, t.transfer_date, f.code as from_code, d.code as to_code, t.status, COUNT(i.id) as items, SUM(i.quantity) as sent,
                SUM(COALESCE(i.received_quantity, 0)) as received, SUM(i.shortage) as shortage, SUM(i.quantity * i.unit_cost) as value, SUM(i.shortage * i.unit_cost) as shortage_value')
            ->orderByDesc('t.transfer_date')->get()
            ->map(fn ($r) => [
                'number' => $r->transfer_no, 'date' => $r->transfer_date, 'route' => "{$r->from_code} → {$r->to_code}", 'status' => StockTransfer::STATUSES[$r->status][0] ?? $r->status,
                'items' => (int) $r->items, 'sent' => (float) $r->sent, 'received' => (float) $r->received, 'shortage' => (float) $r->shortage,
                'value' => round((float) $r->value, 2), 'shortage_value' => round((float) $r->shortage_value, 2), '_url' => route('transfers.show', $r->id),
            ]);

        return new ReportResult(
            cards: [['Transfers', (string) $rows->count(), 'truck', 'primary'], ['Value moved (cost)', money($rows->sum('value')), 'wallet', 'info'], ['Shortage value', money($rows->sum('shortage_value')), 'alert-triangle', 'danger']],
            columns: ['number' => ['Transfer', 'text'], 'date' => ['Date', 'date'], 'route' => ['Route', 'text'], 'status' => ['Status', 'badge'], 'items' => ['Items', 'number'], 'sent' => ['Sent', 'qty'], 'received' => ['Received', 'qty'], 'shortage' => ['Short', 'qty'], 'value' => ['Value', 'money'], 'shortage_value' => ['Shortage value', 'money']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['items', 'sent', 'received', 'shortage', 'value', 'shortage_value']),
        );
    }
}
