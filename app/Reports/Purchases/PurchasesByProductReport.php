<?php

namespace App\Reports\Purchases;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class PurchasesByProductReport extends Report
{
    public string $key = 'purchases-by-product';
    public string $title = 'Purchases by product';
    public string $group = 'Purchases';
    public string $description = 'Quantity bought, amount spent and average / latest cost per product.';
    public string $icon = 'package';

    public function run(ReportContext $ctx): ReportResult
    {
        $rows = DB::table('purchase_items')->join('purchases', 'purchases.id', '=', 'purchase_items.purchase_id')
            ->join('products', 'products.id', '=', 'purchase_items.product_id')
            ->where('purchases.status', 'received')
            ->whereBetween('purchases.purchase_date', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('purchases.branch_id', $ctx->branchId))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.purchase_price')
            ->selectRaw('products.name, products.sku, products.purchase_price, COUNT(DISTINCT purchases.id) as purchases, SUM(purchase_items.quantity) as qty, SUM(purchase_items.line_total) as total')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'name' => $r->name, 'sku' => $r->sku, 'purchases' => (int) $r->purchases, 'qty' => (float) $r->qty, 'total' => round((float) $r->total, 2),
                'avg_cost' => round((float) $r->total / max((float) $r->qty, 0.001), 2), 'last_cost' => (float) $r->purchase_price,
            ]);

        return new ReportResult(
            cards: [['Products bought', (string) $rows->count(), 'package', 'primary'], ['Units', number_format($rows->sum('qty')), 'boxes', 'info'], ['Spent', money($rows->sum('total')), 'wallet', 'warning']],
            columns: ['name' => ['Product', 'text'], 'sku' => ['SKU', 'text'], 'purchases' => ['Purchases', 'number'], 'qty' => ['Qty', 'qty'], 'total' => ['Total cost', 'money'], 'avg_cost' => ['Avg cost', 'money'], 'last_cost' => ['Current cost', 'money']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['purchases', 'qty', 'total']),
        );
    }
}
