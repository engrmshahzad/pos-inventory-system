<?php

namespace App\Reports\Purchases;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class PurchasesBySupplierReport extends Report
{
    public string $key = 'purchases-by-supplier';
    public string $title = 'Purchases by supplier';
    public string $group = 'Purchases';
    public string $description = 'Received purchases, payments and returns per supplier.';
    public string $icon = 'factory';

    public function run(ReportContext $ctx): ReportResult
    {
        $returns = DB::table('purchase_returns')->whereBetween('returned_at', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('branch_id', $ctx->branchId))
            ->groupBy('supplier_id')->selectRaw('supplier_id, SUM(total) as total')->pluck('total', 'supplier_id');

        $rows = DB::table('purchases')->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('purchases.status', 'received')
            ->whereBetween('purchases.purchase_date', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('purchases.branch_id', $ctx->branchId))
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.company', 'suppliers.balance')
            ->selectRaw('suppliers.id, suppliers.name, suppliers.company, suppliers.balance, COUNT(*) as purchases, SUM(purchases.grand_total) as total,
                SUM(purchases.paid_amount) as paid, SUM(purchases.due_amount) as due')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'name' => $r->name, 'company' => $r->company, 'purchases' => (int) $r->purchases, 'total' => round((float) $r->total, 2),
                'paid' => round((float) $r->paid, 2), 'due' => round((float) $r->due, 2), 'returns' => round((float) ($returns[$r->id] ?? 0), 2),
                'balance' => round((float) $r->balance, 2), '_url' => route('suppliers.show', $r->id),
            ]);

        $top = $rows->take(10);

        return new ReportResult(
            cards: [['Suppliers', (string) $rows->count(), 'factory', 'primary'], ['Purchased', money($rows->sum('total')), 'truck', 'info'], ['Still due', money($rows->sum('due')), 'wallet', 'danger'], ['Returned', money($rows->sum('returns')), 'rotate-ccw', 'warning']],
            columns: ['name' => ['Supplier', 'text'], 'company' => ['Company', 'text'], 'purchases' => ['Purchases', 'number'], 'total' => ['Total', 'money'],
                'paid' => ['Paid', 'money'], 'due' => ['Due', 'money'], 'returns' => ['Returns', 'money'], 'balance' => ['Payable now', 'money']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['purchases', 'total', 'paid', 'due', 'returns']),
            chart: $top->isNotEmpty() ? $this->moneyTooltipChart('bar', $top->pluck('name')->all(), [['name' => 'Purchased', 'data' => $top->pluck('total')->all()]], ['plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4]], 'legend' => ['show' => false]]) : null,
        );
    }
}
