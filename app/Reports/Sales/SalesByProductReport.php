<?php

namespace App\Reports\Sales;

use App\Models\Category;
use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;

class SalesByProductReport extends Report
{
    public string $key = 'sales-by-product';
    public string $title = 'Sales by product';
    public string $group = 'Sales';
    public string $description = 'Quantity sold, revenue and margin for each product.';
    public string $icon = 'package';

    public function filters(): array
    {
        return ['category' => ['Category', 'select', ['' => 'All categories'] + Category::roots()->orderBy('name')->pluck('name', 'id')->all(), '']];
    }

    public function run(ReportContext $ctx): ReportResult
    {
        $category = $ctx->param('category');

        $rows = $this->saleItems($ctx)
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->when($category, fn ($q) => $q->where(fn ($q) => $q->where('c.id', $category)->orWhere('c.parent_id', $category)))
            ->groupBy('sale_items.product_id', 'products.name', 'products.sku', 'c.name', 'p.name')
            ->selectRaw("products.name, products.sku, COALESCE(p.name, c.name, '—') as category, SUM(sale_items.quantity) as qty,
                SUM(sale_items.line_total - sale_items.tax_amount) as revenue, SUM(sale_items.purchase_price * sale_items.quantity) as cost,
                COUNT(DISTINCT sale_items.sale_id) as invoices")
            ->orderByDesc('revenue')->get()
            ->map(fn ($r) => array_filter([
                'name' => $r->name, 'sku' => $r->sku, 'category' => $r->category, 'invoices' => (int) $r->invoices,
                'qty' => (float) $r->qty, 'revenue' => round((float) $r->revenue, 2),
                'cost' => $ctx->canProfit ? round((float) $r->cost, 2) : null,
                'profit' => $ctx->canProfit ? round((float) $r->revenue - (float) $r->cost, 2) : null,
                'margin' => $ctx->canProfit ? $this->pct((float) $r->revenue - (float) $r->cost, (float) $r->revenue) : null,
            ], fn ($v) => $v !== null));

        $columns = ['name' => ['Product', 'text'], 'sku' => ['SKU', 'text'], 'category' => ['Category', 'text'], 'invoices' => ['Invoices', 'number'], 'qty' => ['Qty sold', 'qty'], 'revenue' => ['Revenue', 'money']];
        if ($ctx->canProfit) {
            $columns += ['cost' => ['Cost', 'money'], 'profit' => ['Profit', 'money'], 'margin' => ['Margin %', 'percent']];
        }

        $top = $rows->take(10);
        $revenue = $rows->sum('revenue');

        return new ReportResult(
            cards: array_filter([
                ['Products sold', number_format($rows->count()), 'package', 'primary'],
                ['Units sold', number_format($rows->sum('qty'), 0), 'boxes', 'info'],
                ['Revenue (excl. tax)', money($revenue), 'banknote', 'success'],
                $ctx->canProfit ? ['Profit', money($rows->sum('profit')).' · '.$this->pct($rows->sum('profit'), $revenue).'%', 'trending-up', 'success'] : null,
            ]),
            columns: $columns,
            rows: $rows,
            totals: ReportResult::sum($rows, array_values(array_intersect(['invoices', 'qty', 'revenue', 'cost', 'profit'], array_keys($columns)))),
            chart: $top->isNotEmpty() ? $this->moneyTooltipChart('bar', $top->pluck('name')->all(), [['name' => 'Revenue', 'data' => $top->pluck('revenue')->all()]],
                ['plotOptions' => ['bar' => ['horizontal' => true, 'borderRadius' => 4]], 'legend' => ['show' => false]]) : null,
        );
    }
}
