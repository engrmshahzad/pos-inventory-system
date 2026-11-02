<?php

namespace App\Reports\Stock;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class StockValuationReport extends Report
{
    public string $key = 'stock-valuation';
    public string $title = 'Stock valuation';
    public string $group = 'Stock';
    public string $description = 'Value of stock on hand per category, at cost and at sale price.';
    public string $icon = 'wallet';
    public bool $usesDates = false;

    public function run(ReportContext $ctx): ReportResult
    {
        $name = "COALESCE(p.name, c.name, 'Uncategorised')";
        $rows = DB::table('branch_stock')->join('products', 'products.id', '=', 'branch_stock.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->whereNull('products.deleted_at')->where('branch_stock.quantity', '>', 0)
            ->when($ctx->branchId, fn ($q) => $q->where('branch_stock.branch_id', $ctx->branchId))
            ->groupBy(DB::raw($name))
            ->selectRaw("{$name} as name, COUNT(DISTINCT products.id) as products, SUM(branch_stock.quantity) as qty,
                SUM(branch_stock.quantity * products.purchase_price) as cost, SUM(branch_stock.quantity * products.sale_price) as sale")
            ->orderByDesc('cost')->get();

        $totalCost = (float) $rows->sum('cost');
        $data = $rows->map(fn ($r) => [
            'name' => $r->name, 'products' => (int) $r->products, 'qty' => (float) $r->qty, 'cost' => round((float) $r->cost, 2), 'sale' => round((float) $r->sale, 2),
            'potential' => round((float) $r->sale - (float) $r->cost, 2), 'share' => $this->pct((float) $r->cost, $totalCost),
        ]);

        return new ReportResult(
            cards: [['At cost', money($totalCost), 'wallet', 'primary'], ['At sale price', money($data->sum('sale')), 'banknote', 'success'], ['Potential profit', money($data->sum('potential')), 'trending-up', 'info']],
            columns: ['name' => ['Category', 'text'], 'products' => ['Products', 'number'], 'qty' => ['Qty', 'qty'], 'cost' => ['Value (cost)', 'money'], 'sale' => ['Value (sale)', 'money'], 'potential' => ['Potential profit', 'money'], 'share' => ['Share %', 'percent']],
            rows: $data,
            totals: ReportResult::sum($data, ['products', 'qty', 'cost', 'sale', 'potential']),
            chart: $data->isNotEmpty() ? $this->donut($data->pluck('name')->all(), $data->pluck('cost')->all()) : null,
        );
    }
}
