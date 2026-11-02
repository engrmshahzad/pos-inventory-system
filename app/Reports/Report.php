<?php

namespace App\Reports;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

abstract class Report
{
    public string $key;
    public string $title;
    public string $group;
    public string $description = '';
    public string $icon = 'bar-chart';

    /** Report shows profit/cost figures: needs reports.profit. */
    public bool $needsProfit = false;
    public bool $ownerOnly = false;
    public bool $usesDates = true;

    /** Extra filters: name => [label, type (select|number), options/default] */
    public function filters(): array
    {
        return [];
    }

    abstract public function run(ReportContext $ctx): ReportResult;

    public function allowedFor(\App\Models\User $user): bool
    {
        if ($this->ownerOnly && ! $user->isOwner()) {
            return false;
        }

        return $user->can('reports.view') && (! $this->needsProfit || $user->can('reports.profit'));
    }

    /** Completed sales in the period (and branch). */
    protected function sales(ReportContext $ctx): Builder
    {
        return DB::table('sales')->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('sales.branch_id', $ctx->branchId));
    }

    /** Sale lines of completed sales in the period. */
    protected function saleItems(ReportContext $ctx): Builder
    {
        return DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')
            ->whereBetween('sales.sold_at', [$ctx->start(), $ctx->end()])
            ->when($ctx->branchId, fn ($q) => $q->where('sales.branch_id', $ctx->branchId));
    }

    protected function money(float $value): string
    {
        return money($value);
    }

    protected function moneyTooltipChart(string $type, array $labels, array $series, array $extra = []): array
    {
        return array_replace_recursive([
            'chart' => ['type' => $type, 'height' => 300],
            'series' => $series,
            'xaxis' => ['categories' => $labels],
            'stroke' => ['curve' => 'smooth', 'width' => 2.5],
            'legend' => ['position' => 'top', 'horizontalAlign' => 'right'],
            'moneyTooltip' => true,
        ], $extra);
    }

    protected function donut(array $labels, array $values): array
    {
        return [
            'chart' => ['type' => 'donut', 'height' => 300],
            'series' => array_map('floatval', $values),
            'labels' => $labels,
            'legend' => ['position' => 'right'],
            'stroke' => ['width' => 0],
            'plotOptions' => ['pie' => ['donut' => ['size' => '68%']]],
            'moneyDonut' => true,
        ];
    }

    protected function pct(float $part, float $whole): float
    {
        return $whole != 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
