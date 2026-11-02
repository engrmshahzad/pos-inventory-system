<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Support\Sql;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard numbers for one branch (or all branches). Aggregate queries only; cached for 5 minutes.
 * "Profit" here = gross profit on sales (revenue excl. tax & charges − cost snapshot). The P&L report also deducts returns, expenses and stock losses.
 */
class DashboardService
{
    public const TTL = 300;

    public function cacheKey(?int $branchId): string
    {
        return 'dashboard:'.($branchId ?? 'all').':'.today()->toDateString();
    }

    public function forget(?int $branchId): void
    {
        Cache::forget($this->cacheKey($branchId));
    }

    public function metrics(?int $branchId): array
    {
        return Cache::remember($this->cacheKey($branchId), self::TTL, fn () => $this->build($branchId) + ['generated_at' => now()->toIso8601String()]);
    }

    protected function build(?int $branchId): array
    {
        $today = $this->salesTotals($branchId, today(), today()->endOfDay());
        $yesterday = $this->salesTotals($branchId, today()->subDay(), today()->subDay()->endOfDay());
        $expToday = $this->expenses($branchId, today(), today());
        $expYesterday = $this->expenses($branchId, today()->subDay(), today()->subDay());

        return [
            'kpis' => [
                'sales' => [$today['sales'], $this->trend($today['sales'], $yesterday['sales'])],
                'profit' => [$today['profit'], $this->trend($today['profit'], $yesterday['profit'])],
                'orders' => [$today['orders'], $this->trend($today['orders'], $yesterday['orders'])],
                'expenses' => [$expToday, $this->trend($expToday, $expYesterday)],
            ],
            'balances' => [
                'receivable' => $branchId
                    ? (float) Sale::completed()->where('branch_id', $branchId)->sum('due_amount')
                    : (float) Customer::where('balance', '>', 0)->sum('balance'),
                'payable' => $branchId
                    ? (float) Purchase::received()->where('branch_id', $branchId)->sum('due_amount')
                    : (float) Supplier::where('balance', '>', 0)->sum('balance'),
                'stock_value' => (float) $this->stockRows($branchId)->where('branch_stock.quantity', '>', 0)->sum(DB::raw('branch_stock.quantity * products.purchase_price')),
                'low_stock' => $this->stockRows($branchId)->whereColumn('branch_stock.quantity', '<=', 'products.min_stock')->where('products.is_active', true)->count(),
            ],
            'daily' => $this->daily($branchId, 30),
            'monthly' => $this->monthly($branchId, 12),
            'branches' => $branchId ? [] : $this->branchComparison(),
            'methods' => $this->paymentMethods($branchId, 30),
            'top_products' => $this->topProducts($branchId, 30),
            'categories' => $this->categories($branchId, 30),
            'hourly' => $this->hourly($branchId, 30),
            'top_customers' => $this->topCustomers($branchId, 30),
        ];
    }

    /** @return array{sales: float, profit: float, orders: int} */
    public function salesTotals(?int $branchId, Carbon $from, Carbon $to): array
    {
        $sales = DB::table('sales')->where('status', 'completed')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);

        $row = (clone $sales)->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total),0) as sales, COALESCE(SUM(grand_total - tax_amount - shipping),0) as net')->first();
        $cost = (float) DB::table('sale_items')->whereIn('sale_id', (clone $sales)->select('id'))->sum(DB::raw('purchase_price * quantity'));

        return ['sales' => (float) $row->sales, 'profit' => round((float) $row->net - $cost, 2), 'orders' => (int) $row->orders];
    }

    public function expenses(?int $branchId, Carbon $from, Carbon $to): float
    {
        return (float) Expense::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereBetween('expense_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->sum('amount');
    }

    protected function trend(float $now, float $before): ?float
    {
        if ($before == 0) {
            return $now > 0 ? 100.0 : null;
        }

        return round(($now - $before) / abs($before) * 100, 1);
    }

    protected function stockRows(?int $branchId): QueryBuilder
    {
        return DB::table('branch_stock')
            ->join('products', 'products.id', '=', 'branch_stock.product_id')
            ->join('branches', 'branches.id', '=', 'branch_stock.branch_id')
            ->whereNull('products.deleted_at')->whereNull('branches.deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_stock.branch_id', $branchId));
    }

    /** Sales and gross profit per day for the last $days days (zero-filled). */
    protected function daily(?int $branchId, int $days): array
    {
        $from = today()->subDays($days - 1);
        $rows = $this->grouped($branchId, $from, Sql::day('sales.sold_at'));

        $labels = $sales = $profit = [];
        foreach (range($days - 1, 0) as $d) {
            $date = today()->subDays($d);
            $key = $date->toDateString();
            $labels[] = $date->format('d M');
            $sales[] = round((float) ($rows[$key]->sales ?? 0), 2);
            $profit[] = round((float) ($rows[$key]->profit ?? 0), 2);
        }

        return compact('labels', 'sales', 'profit');
    }

    protected function monthly(?int $branchId, int $months): array
    {
        $from = today()->subMonthsNoOverflow($months - 1)->startOfMonth();
        $rows = $this->grouped($branchId, $from, Sql::month('sales.sold_at'));

        $labels = $sales = $profit = [];
        foreach (range($months - 1, 0) as $m) {
            $month = today()->subMonthsNoOverflow($m);
            $key = $month->format('Y-m');
            $labels[] = $month->format('M y');
            $sales[] = round((float) ($rows[$key]->sales ?? 0), 2);
            $profit[] = round((float) ($rows[$key]->profit ?? 0), 2);
        }

        return compact('labels', 'sales', 'profit');
    }

    /** Sales + profit grouped by a date expression, keyed by the period. */
    protected function grouped(?int $branchId, Carbon $from, string $periodExpr)
    {
        $cost = DB::table('sale_items')->selectRaw('sale_id, SUM(purchase_price * quantity) as cost')->groupBy('sale_id');

        return DB::table('sales')
            ->leftJoinSub($cost, 'c', 'c.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')
            ->where('sales.sold_at', '>=', $from)
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->groupBy(DB::raw($periodExpr))
            ->selectRaw("{$periodExpr} as period, SUM(sales.grand_total) as sales, SUM(sales.grand_total - sales.tax_amount - sales.shipping - COALESCE(c.cost,0)) as profit")
            ->get()->keyBy('period');
    }

    protected function branchComparison(): array
    {
        $from = today()->startOfMonth();
        $cost = DB::table('sale_items')->selectRaw('sale_id, SUM(purchase_price * quantity) as cost')->groupBy('sale_id');
        $rows = DB::table('sales')->leftJoinSub($cost, 'c', 'c.sale_id', '=', 'sales.id')
            ->where('sales.status', 'completed')->where('sales.sold_at', '>=', $from)
            ->groupBy('sales.branch_id')
            ->selectRaw('sales.branch_id, SUM(sales.grand_total) as sales, SUM(sales.grand_total - sales.tax_amount - sales.shipping - COALESCE(c.cost,0)) as profit')
            ->get()->keyBy('branch_id');

        return Branch::orderBy('name')->get(['id', 'name', 'code'])->map(fn ($b) => [
            'name' => $b->code, 'sales' => round((float) ($rows[$b->id]->sales ?? 0), 2), 'profit' => round((float) ($rows[$b->id]->profit ?? 0), 2),
        ])->values()->all();
    }

    protected function paymentMethods(?int $branchId, int $days): array
    {
        $rows = DB::table('payments')->whereIn('type', ['sale', 'customer_receipt'])
            ->where('paid_at', '>=', today()->subDays($days - 1))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy('method')->selectRaw('method, SUM(amount) as total')->pluck('total', 'method');

        $credit = (float) DB::table('sales')->where('status', 'completed')->where('sold_at', '>=', today()->subDays($days - 1))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->sum('due_amount');

        $labels = $values = [];
        foreach (Payment::METHODS as $key => $label) {
            if ((float) ($rows[$key] ?? 0) > 0) {
                $labels[] = $label;
                $values[] = round((float) $rows[$key], 2);
            }
        }
        if ($credit > 0) {
            $labels[] = 'Credit (unpaid)';
            $values[] = round($credit, 2);
        }

        return compact('labels', 'values');
    }

    protected function topProducts(?int $branchId, int $days): array
    {
        $rows = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.status', 'completed')->where('sales.sold_at', '>=', today()->subDays($days - 1))
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->groupBy('sale_items.product_id', 'sale_items.product_name')
            ->selectRaw('sale_items.product_name as name, SUM(sale_items.quantity) as qty, SUM(sale_items.line_total - sale_items.tax_amount) as revenue')
            ->orderByDesc('revenue')->limit(10)->get();

        return ['labels' => $rows->pluck('name')->all(), 'values' => $rows->map(fn ($r) => round((float) $r->revenue, 2))->all()];
    }

    protected function categories(?int $branchId, int $days): array
    {
        $rows = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->leftJoin('categories as c', 'c.id', '=', 'products.category_id')
            ->leftJoin('categories as p', 'p.id', '=', 'c.parent_id')
            ->where('sales.status', 'completed')->where('sales.sold_at', '>=', today()->subDays($days - 1))
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->groupBy(DB::raw("COALESCE(p.name, c.name, 'Uncategorised')"))
            ->selectRaw("COALESCE(p.name, c.name, 'Uncategorised') as name, SUM(sale_items.line_total - sale_items.tax_amount) as revenue")
            ->orderByDesc('revenue')->get();

        return ['labels' => $rows->pluck('name')->all(), 'values' => $rows->map(fn ($r) => round((float) $r->revenue, 2))->all()];
    }

    protected function hourly(?int $branchId, int $days): array
    {
        $hour = Sql::hour('sold_at');
        $rows = DB::table('sales')->where('status', 'completed')->where('sold_at', '>=', today()->subDays($days - 1))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->groupBy(DB::raw($hour))->selectRaw("{$hour} as h, COUNT(*) as orders, SUM(grand_total) as sales")->get()->keyBy('h');

        $labels = $orders = [];
        foreach (range(8, 23) as $h) {
            $labels[] = Carbon::createFromTime($h)->format('g A');
            $orders[] = (int) ($rows[$h]->orders ?? 0);
        }

        return compact('labels', 'orders');
    }

    protected function topCustomers(?int $branchId, int $days): array
    {
        return DB::table('sales')->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.status', 'completed')->where('sales.sold_at', '>=', today()->subDays($days - 1))
            ->when($branchId, fn ($q) => $q->where('sales.branch_id', $branchId))
            ->groupBy('customers.id', 'customers.name', 'customers.balance')
            ->selectRaw('customers.id, customers.name, customers.balance, COUNT(*) as orders, SUM(sales.grand_total) as total')
            ->orderByDesc('total')->limit(5)->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'orders' => (int) $r->orders, 'total' => (float) $r->total, 'balance' => (float) $r->balance])->all();
    }
}
