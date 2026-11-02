<?php

namespace App\Reports\Stock;

use App\Reports\Report;
use App\Reports\ReportContext;
use App\Reports\ReportResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DeadStockReport extends Report
{
    public string $key = 'dead-stock';
    public string $title = 'Dead stock';
    public string $group = 'Stock';
    public string $description = 'Products in stock that have not sold for N days — money sitting on the shelf.';
    public string $icon = 'clock';
    public bool $usesDates = false;

    public function filters(): array
    {
        return ['days' => ['No sale in', 'select', ['30' => '30 days', '60' => '60 days', '90' => '90 days', '180' => '180 days'], '60']];
    }

    public function run(ReportContext $ctx): ReportResult
    {
        $days = (int) $ctx->param('days', 60);
        $since = today()->subDays($days);

        $lastSale = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')->where('sales.status', 'completed')
            ->groupBy('sales.branch_id', 'sale_items.product_id')
            ->selectRaw('sales.branch_id, sale_items.product_id, MAX(sales.sold_at) as last_sold');

        $rows = DB::table('branch_stock')->join('products', 'products.id', '=', 'branch_stock.product_id')
            ->join('branches', 'branches.id', '=', 'branch_stock.branch_id')
            ->leftJoinSub($lastSale, 'ls', fn ($j) => $j->on('ls.product_id', '=', 'branch_stock.product_id')->on('ls.branch_id', '=', 'branch_stock.branch_id'))
            ->whereNull('products.deleted_at')->whereNull('branches.deleted_at')->where('branch_stock.quantity', '>', 0)
            ->where(fn ($q) => $q->whereNull('ls.last_sold')->orWhere('ls.last_sold', '<', $since))
            ->when($ctx->branchId, fn ($q) => $q->where('branch_stock.branch_id', $ctx->branchId))
            ->orderByDesc(DB::raw('branch_stock.quantity * products.purchase_price'))
            ->get(['products.name', 'products.sku', 'branches.code as branch', 'branch_stock.quantity', 'products.purchase_price', 'ls.last_sold'])
            ->map(fn ($r) => [
                'name' => $r->name, 'sku' => $r->sku, 'branch' => $r->branch, 'qty' => (float) $r->quantity,
                'value' => round((float) $r->quantity * (float) $r->purchase_price, 2),
                'last_sold' => $r->last_sold, 'days' => $r->last_sold ? (int) Carbon::parse($r->last_sold)->diffInDays(today()) : null,
            ]);

        return new ReportResult(
            cards: [['Products', (string) $rows->count(), 'package', 'warning'], ['Value tied up (cost)', money($rows->sum('value')), 'wallet', 'danger']],
            columns: ['name' => ['Product', 'text'], 'sku' => ['SKU', 'text'], 'branch' => ['Branch', 'text'], 'qty' => ['In stock', 'qty'], 'value' => ['Value (cost)', 'money'], 'last_sold' => ['Last sold', 'date'], 'days' => ['Days since', 'number']],
            rows: $rows,
            totals: ReportResult::sum($rows, ['qty', 'value']),
            note: "Never sold, or last sold more than {$days} days ago. Consider a discount or a transfer to a busier branch.",
        );
    }
}
