<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request): View
    {
        $branchId = currentBranchId();
        $matrix = $branchId === null; // owner on "All Branches"
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true) ? (int) $request->input('per_page') : 25;
        $branches = $matrix ? Branch::orderBy('name')->get(['id', 'name', 'code']) : collect();

        $products = Product::query()
            ->withStock($branchId)
            ->with(['category:id,name,color', 'unit:id,short_name,allow_decimal'])
            ->when($matrix, fn ($q) => $q->with(['stocks' => fn ($s) => $s->select('id', 'product_id', 'branch_id', 'quantity')]))
            ->search($request->input('search'))
            ->when($request->filled('category'), function (Builder $q) use ($request) {
                $id = $request->integer('category');
                $q->where(fn ($q) => $q->where('category_id', $id)->orWhereIn('category_id', Category::where('parent_id', $id)->select('id')));
            })
            ->when($request->filled('stock'), fn (Builder $q) => $q->stockStatus($request->input('stock'), $branchId))
            ->when($request->input('sort') === 'qty', fn ($q) => $q->orderBy('stock_qty'))
            ->when($request->input('sort') === 'value', fn ($q) => $q->orderByRaw('stock_qty * purchase_price DESC'))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return view('stock.index', [
            'products' => $products,
            'matrix' => $matrix,
            'branches' => $branches,
            'branchCount' => $matrix ? max($branches->count(), 1) : 1,
            'categories' => Category::orderBy('name')->pluck('name', 'id'),
            'stats' => $this->stats($branchId),
            'lowStock' => $this->lowStockList($branchId),
        ]);
    }

    public function movements(Request $request): View
    {
        $branchId = currentBranchId();
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'type' => ['nullable', 'in:'.implode(',', array_keys(StockMovement::TYPES))],
        ]);

        $movements = StockMovement::query()
            ->with(['product:id,name,sku,unit_id', 'product.unit:id,short_name,allow_decimal', 'branch:id,name,code', 'user:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($request->filled('product'), fn ($q) => $q->where('product_id', $request->integer('product')))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('from'), fn ($q) => $q->where('created_at', '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('created_at', '<=', $request->date('to')->endOfDay()))
            ->latest('id')
            ->paginate(in_array((int) $request->input('per_page'), [10, 25, 50, 100], true) ? (int) $request->input('per_page') : 25)
            ->withQueryString();

        $users = User::query()
            ->when($branchId, fn ($q) => $q->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderBy('name')
            ->pluck('name', 'id');

        return view('stock.movements', [
            'movements' => $movements,
            'users' => $users,
            'selectedProduct' => $request->filled('product') ? Product::withTrashed()->find($request->integer('product'), ['id', 'name', 'sku']) : null,
        ]);
    }

    /** Totals over branch_stock rows in scope (one branch, or every branch). */
    protected function stats(?int $branchId): array
    {
        $base = fn () => BranchStock::query()
            ->join('products', 'products.id', '=', 'branch_stock.product_id')
            ->join('branches', 'branches.id', '=', 'branch_stock.branch_id')
            ->whereNull('products.deleted_at')
            ->whereNull('branches.deleted_at')
            ->when($branchId, fn ($q) => $q->where('branch_stock.branch_id', $branchId));

        $values = $base()->selectRaw('
            COALESCE(SUM(CASE WHEN branch_stock.quantity > 0 THEN branch_stock.quantity * products.purchase_price ELSE 0 END), 0) AS cost_value,
            COALESCE(SUM(CASE WHEN branch_stock.quantity > 0 THEN branch_stock.quantity * products.sale_price ELSE 0 END), 0) AS sale_value,
            COALESCE(SUM(CASE WHEN branch_stock.quantity > 0 AND branch_stock.quantity <= products.min_stock THEN 1 ELSE 0 END), 0) AS low_count,
            COALESCE(SUM(CASE WHEN branch_stock.quantity <= 0 THEN 1 ELSE 0 END), 0) AS out_count
        ')->first();

        return [
            'products' => Product::active()->count(),
            'cost_value' => (float) $values->cost_value,
            'sale_value' => (float) $values->sale_value,
            'low' => (int) $values->low_count,
            'out' => (int) $values->out_count,
        ];
    }

    protected function lowStockList(?int $branchId)
    {
        return BranchStock::query()
            ->select('branch_stock.*')
            ->join('products', 'products.id', '=', 'branch_stock.product_id')
            ->join('branches', 'branches.id', '=', 'branch_stock.branch_id')
            ->whereNull('products.deleted_at')
            ->whereNull('branches.deleted_at')
            ->where('products.is_active', true)
            ->whereColumn('branch_stock.quantity', '<=', 'products.min_stock')
            ->when($branchId, fn ($q) => $q->where('branch_stock.branch_id', $branchId))
            ->with(['product:id,name,sku,min_stock,unit_id', 'product.unit:id,short_name,allow_decimal', 'branch:id,name,code'])
            ->orderBy(DB::raw('branch_stock.quantity / NULLIF(products.min_stock, 0)'))
            ->limit(8)
            ->get();
    }
}
