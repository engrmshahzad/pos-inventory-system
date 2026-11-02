<?php

namespace App\Reports\Sales;

use App\Models\Payment;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class SalesByPaymentMethodReport extends Report
{
    public string $key = 'sales-by-payment-method';
    public string $title = 'Sales by payment method';
    public string $group = 'Sales';
    public string $description = 'Money received per method (at sale and later), refunds paid out, and credit left unpaid.';
    public string $icon = 'coins';

    public function run(ReportContext $ctx): ReportResult
    {
        $base = DB::table('payments')->whereBetween('paid_at', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('branch_id', $ctx->branchId));

        $in = (clone $base)->whereIn('type', ['sale', 'customer_receipt'])->groupBy('method')
            ->selectRaw('method, COUNT(*) as count, SUM(amount) as total')->get()->keyBy('method');
        $out = (clone $base)->where('type', 'refund')->groupBy('method')->selectRaw('method, SUM(amount) as total')->pluck('total', 'method');

        $rows = collect(Payment::METHODS)->map(fn ($label, $key) => [
            'method' => $label, 'count' => (int) ($in[$key]->count ?? 0), 'received' => round((float) ($in[$key]->total ?? 0), 2),
            'refunded' => round((float) ($out[$key] ?? 0), 2), 'net' => round((float) ($in[$key]->total ?? 0) - (float) ($out[$key] ?? 0), 2),
        ])->filter(fn ($r) => $r['received'] > 0 || $r['refunded'] > 0)->values();

        $total = $rows->sum('received');
        $rows = $rows->map(fn ($r) => $r + ['share' => $this->pct($r['received'], $total)]);
        $credit = (float) $this->sales($ctx)->sum('due_amount');

        return new ReportResult(
            cards: [
                ['Received', money($total), 'trending-up', 'success'],
                ['Refunded', money($rows->sum('refunded')), 'undo', 'warning'],
                ['Credit still unpaid', money($credit), 'file-text', 'danger'],
            ],
            columns: ['method' => ['Method', 'text'], 'count' => ['Payments', 'number'], 'received' => ['Received', 'money'], 'share' => ['Share %', 'percent'], 'refunded' => ['Refunded', 'money'], 'net' => ['Net', 'money']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['count', 'received', 'refunded', 'net']),
            chart: $rows->isNotEmpty() ? $this->donut($rows->pluck('method')->all(), $rows->pluck('received')->all()) : null,
        );
    }
}
