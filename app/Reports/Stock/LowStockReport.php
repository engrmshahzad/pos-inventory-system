<?php

namespace App\Reports\Stock;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class LowStockReport extends Report
{
    public string $key = 'low-stock';
    public string $title = 'Low stock';
    public string $group = 'Stock';
    public string $description = 'Products at or below their low-stock level in each branch, with the quantity to reorder.';
    public string $icon = 'alert-triangle';
    public bool $usesDates = false;

    public function run(ReportContext $ctx): ReportResult
    {
        $rows = DB::table('branch_stock')->join('products', 'products.id', '=', 'branch_stock.product_id')
            ->join('branches', 'branches.id', '=', 'branch_stock.branch_id')
            ->whereNull('products.deleted_at')->whereNull('branches.deleted_at')->where('products.is_active', true)->where('products.min_stock', '>', 0)
            ->whereColumn('branch_stock.quantity', '<=', 'products.min_stock')
            ->when($ctx->branchId, fn ($q) => $q->where('branch_stock.branch_id', $ctx->branchId))
            ->orderBy('branch_stock.quantity')->orderBy('products.name')
            ->get(['products.name', 'products.sku', 'branches.code as branch', 'branch_stock.quantity', 'products.min_stock', 'products.purchase_price'])
            ->map(fn ($r) => [
                'name' => $r->name, 'sku' => $r->sku, 'branch' => $r->branch, 'qty' => (float) $r->quantity, 'min' => (float) $r->min_stock,
                'reorder' => max(0, 2 * (float) $r->min_stock - (float) $r->quantity),
                'reorder_cost' => round(max(0, 2 * (float) $r->min_stock - (float) $r->quantity) * (float) $r->purchase_price, 2),
                'status' => (float) $r->quantity <= 0 ? 'Out' : 'Low',
            ]);

        return new ReportResult(
            cards: [['Low', (string) $rows->where('status', 'Low')->count(), 'alert-triangle', 'warning'], ['Out of stock', (string) $rows->where('status', 'Out')->count(), 'x-circle', 'danger'], ['Reorder cost (to 2× level)', money($rows->sum('reorder_cost')), 'truck', 'info']],
            columns: ['name' => ['Product', 'text'], 'sku' => ['SKU', 'text'], 'branch' => ['Branch', 'text'], 'qty' => ['In stock', 'qty'], 'min' => ['Low level', 'qty'], 'reorder' => ['Suggested order', 'qty'], 'reorder_cost' => ['Approx. cost', 'money'], 'status' => ['Status', 'badge']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['reorder', 'reorder_cost']),
            note: 'Suggested order brings each item back to twice its low-stock level.',
        );
    }
}
