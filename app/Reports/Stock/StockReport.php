<?php

namespace App\Reports\Stock;

use App\Models\Category;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class StockReport extends Report
{
    public string $key = 'stock';
    public string $title = 'Stock report';
    public string $group = 'Stock';
    public string $description = 'Current quantity and value of every product (branch or all branches).';
    public string $icon = 'boxes';
    public bool $usesDates = false;

    public function filters(): array
    {
        return [
            'category' => ['Category', 'select', ['' => 'All categories'] + Category::roots()->orderBy('name')->pluck('name', 'id')->all(), ''],
            'status' => ['Status', 'select', ['' => 'Any', 'in' => 'In stock', 'low' => 'Low', 'out' => 'Out'], ''],
        ];
    }

    public function run(ReportContext $ctx): ReportResult
    {
        $category = $ctx->param('category');
        $status = $ctx->param('status');
        $branches = $ctx->branchId ? 1 : max(DB::table('branches')->whereNull('deleted_at')->count(), 1);

        $rows = DB::table('products')
            ->leftJoin('branch_stock', fn ($j) => $j->on('branch_stock.product_id', '=', 'products.id')->when($ctx->branchId, fn ($j) => $j->where('branch_stock.branch_id', $ctx->branchId)))
            ->leftJoin('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->whereNull('products.deleted_at')
            ->when($category, fn ($q) => $q->where(fn ($q) => $q->where('c.id', $category)->orWhere('c.parent_id', $category)))
            ->groupBy('products.id', 'products.name', 'products.sku', 'products.min_stock', 'products.purchase_price', 'products.sale_price', 'c.name', 'p.name')
            ->selectRaw("products.name, products.sku, COALESCE(p.name, c.name, '—') as category, products.min_stock, products.purchase_price, products.sale_price, COALESCE(SUM(branch_stock.quantity), 0) as qty")
            ->orderBy('products.name')->get()
            ->map(function ($r) use ($branches) {
                $qty = (float) $r->qty;
                $min = (float) $r->min_stock * $branches;

                return [
                    'name' => $r->name, 'sku' => $r->sku, 'category' => $r->category, 'qty' => $qty, 'min' => $min,
                    'cost_value' => round(max($qty, 0) * (float) $r->purchase_price, 2), 'sale_value' => round(max($qty, 0) * (float) $r->sale_price, 2),
                    'status' => $qty <= 0 ? 'Out' : ($qty <= $min ? 'Low' : 'In stock'),
                ];
            })
            ->when($status, fn ($c) => $c->filter(fn ($r) => $r['status'] === ['in' => 'In stock', 'low' => 'Low', 'out' => 'Out'][$status])->values());

        return new ReportResult(
            cards: [
                ['Products', number_format($rows->count()), 'package', 'primary'],
                ['Stock value (cost)', money($rows->sum('cost_value')), 'wallet', 'info'],
                ['Stock value (sale)', money($rows->sum('sale_value')), 'banknote', 'success'],
                ['Low / out', $rows->where('status', 'Low')->count().' / '.$rows->where('status', 'Out')->count(), 'alert-triangle', 'danger'],
            ],
            columns: ['name' => ['Product', 'text'], 'sku' => ['SKU', 'text'], 'category' => ['Category', 'text'], 'qty' => ['Qty', 'qty'], 'min' => ['Low level', 'qty'],
                'cost_value' => ['Value (cost)', 'money'], 'sale_value' => ['Value (sale)', 'money'], 'status' => ['Status', 'badge']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['qty', 'cost_value', 'sale_value']),
        );
    }
}
