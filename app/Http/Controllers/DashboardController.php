<?php

namespace App\Http\Controllers;

use App\Models\BranchStock;
use App\Models\CashRegister;
use App\Models\Sale;
use App\Models\StockTransfer;
use App\Services\DashboardService;
use App\Services\ExpiryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardService $dashboard, ExpiryService $expiry): View
    {
        $user = $request->user();
        $branchId = currentBranchId();
        $hour = (int) now()->format('G');
        $greeting = match (true) {
            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',
        };

        if ($user->isCashier()) {
            return $this->cashier($request, $greeting);
        }

        return view('dashboard', [
            'greeting' => $greeting,
            'm' => $dashboard->metrics($branchId),
            'canProfit' => $user->can('reports.profit'),
            'recentSales' => Sale::completed()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->with(['customer:id,name', 'branch:id,code'])->latest('sold_at')->limit(8)->get(),
            'lowStock' => BranchStock::query()->select('branch_stock.*')
                ->join('products', 'products.id', '=', 'branch_stock.product_id')
                ->whereNull('products.deleted_at')->where('products.is_active', true)->where('products.min_stock', '>', 0)
                ->whereColumn('branch_stock.quantity', '<=', 'products.min_stock')
                ->when($branchId, fn ($q) => $q->where('branch_stock.branch_id', $branchId))
                ->with(['product:id,name,min_stock,unit_id', 'product.unit:id,short_name,allow_decimal', 'branch:id,code'])
                ->orderBy(DB::raw('branch_stock.quantity / products.min_stock'))->limit(6)->get(),
            'incoming' => StockTransfer::where('status', 'sent')->when($branchId, fn ($q) => $q->where('to_branch_id', $branchId))
                ->with(['fromBranch:id,code', 'toBranch:id,code'])->withCount('items')->latest('sent_at')->limit(5)->get(),
            'expiring' => $expiry->batches($branchId)->take(5),
        ]);
    }

    public function refresh(DashboardService $dashboard): RedirectResponse
    {
        $dashboard->forget(currentBranchId());

        return back()->with('success', 'Dashboard refreshed.');
    }

    protected function cashier(Request $request, string $greeting): View
    {
        $user = $request->user();
        $own = Sale::completed()->where('user_id', $user->id)->where('sold_at', '>=', today());

        return view('dashboard-cashier', [
            'greeting' => $greeting,
            'stats' => (clone $own)->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(due_amount),0) as credit')->first(),
            'register' => CashRegister::openFor($user, $user->branch_id),
            'recent' => (clone $own)->with('customer:id,name')->latest('sold_at')->limit(8)->get(),
        ]);
    }
}
