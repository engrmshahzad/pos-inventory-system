<?php

namespace App\Reports\Sales;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Facades\DB;

class SalesByCategoryReport extends Report
{
    public string $key = 'sales-by-category';
    public string $title = 'Sales by category';
    public string $group = 'Sales';
    public string $description = 'Revenue and profit per top-level category.';
    public string $icon = 'folder';

    public function run(ReportContext $ctx): ReportResult
    {
        $name = "COALESCE(p.name, c.name, 'Uncategorised')";
        $rows = $this->saleItems($ctx)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->groupBy(DB::raw($name))
            ->selectRaw("{$name} as name, COUNT(DISTINCT sale_items.product_id) as products, SUM(sale_items.quantity) as qty,
                SUM(sale_items.line_total - sale_items.tax_amount) as revenue, SUM(sale_items.purchase_price * sale_items.quantity) as cost")
            ->orderByDesc('revenue')->get();

        $revenue = (float) $rows->sum('revenue');
        $data = $rows->map(fn ($r) => array_filter([
            'name' => $r->name, 'products' => (int) $r->products, 'qty' => (float) $r->qty, 'revenue' => round((float) $r->revenue, 2),
            'share' => $this->pct((float) $r->revenue, $revenue),
            'profit' => $ctx->canProfit ? round((float) $r->revenue - (float) $r->cost, 2) : null,
            'margin' => $ctx->canProfit ? $this->pct((float) $r->revenue - (float) $r->cost, (float) $r->revenue) : null,
        ], fn ($v) => $v !== null));

        $columns = ['name' => ['Category', 'text'], 'products' => ['Products', 'number'], 'qty' => ['Qty sold', 'qty'], 'revenue' => ['Revenue', 'money'], 'share' => ['Share %', 'percent']];
        if ($ctx->canProfit) {
            $columns += ['profit' => ['Profit', 'money'], 'margin' => ['Margin %', 'percent']];
        }

        return new ReportResult(
            cards: [['Categories', (string) $data->count(), 'folder', 'primary'], ['Revenue (excl. tax)', money($revenue), 'banknote', 'success']],
            columns: $columns,
            rows: $data,
            totals: ReportResult::sum($data, array_values(array_intersect(['products', 'qty', 'revenue', 'profit'], array_keys($columns)))),
            chart: $data->isNotEmpty() ? $this->donut($data->pluck('name')->all(), $data->pluck('revenue')->all()) : null,
        );
    }
}
