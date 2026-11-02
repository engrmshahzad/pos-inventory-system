<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\StockCount;
use App\Services\StockCountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockCountController extends Controller
{
    public function __construct(protected StockCountService $service)
    {
    }

    public function index(Request $request): View
    {
        return view('stock-counts.index', [
            'counts' => StockCount::visibleTo($request->user())
                ->with(['branch:id,code,name', 'category:id,name', 'creator:id,name', 'adjustment:id,adjustment_no,status,total_value'])
                ->withCount(['items', 'items as counted_count' => fn ($q) => $q->whereNotNull('counted_quantity')])
                ->latest('id')->paginate(20),
            'categories' => Category::roots()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = $this->workingBranch();
        $data = $request->validate([
            'category_id' => ['nullable', 'exists:categories,id'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $count = $this->service->start($request->user(), $branch, $data['category_id'] ?? null, $data['note'] ?? null);

        return redirect()->route('stock-counts.show', $count)->with('success', "{$count->count_no} started with {$count->items()->count()} products. Print the sheet or enter counts below.");
    }

    public function show(Request $request, StockCount $stockCount): View
    {
        $this->ensureBranchAccess($stockCount->branch_id);
        $stockCount->load(['branch', 'category', 'creator', 'adjustment']);

        $items = $stockCount->items()->with(['product:id,name,sku,barcode,unit_id,purchase_price', 'product.unit:id,short_name,allow_decimal'])
            ->join('products', 'products.id', '=', 'stock_count_items.product_id')
            ->orderBy('products.name')->select('stock_count_items.*')->get();

        $counted = $items->whereNotNull('counted_quantity');

        return view('stock-counts.show', [
            'count' => $stockCount,
            'items' => $items,
            'summary' => [
                'total' => $items->count(),
                'counted' => $counted->count(),
                'differences' => $counted->filter(fn ($i) => $i->difference != 0)->count(),
                'value' => round($counted->sum(fn ($i) => $i->difference * (float) $i->product?->purchase_price), 2),
            ],
        ]);
    }

    public function update(Request $request, StockCount $stockCount): RedirectResponse
    {
        $this->ensureBranchAccess($stockCount->branch_id);
        $data = $request->validate(['counted' => ['array'], 'counted.*' => ['nullable', 'numeric', 'min:0']]);

        $this->service->saveCounts($stockCount, $data['counted'] ?? []);

        if ($request->input('then') === 'apply') {
            try {
                $stockCount = $this->service->apply($stockCount, $request->user());
            } catch (InsufficientStockException $e) {
                return back()->withErrors(['action' => $e->getMessage()]);
            }
            $adj = $stockCount->adjustment;

            return redirect()->route('stock-counts.show', $stockCount)->with($adj?->status === 'pending' ? 'warning' : 'success', match (true) {
                ! $adj => 'Count applied. No differences found.',
                $adj->status === 'pending' => "Count applied as {$adj->adjustment_no}, which is waiting for owner approval.",
                default => "Count applied. Stock corrected through {$adj->adjustment_no}.",
            });
        }

        return back()->with('success', 'Counts saved.');
    }

    public function cancel(StockCount $stockCount): RedirectResponse
    {
        $this->ensureBranchAccess($stockCount->branch_id);
        $this->service->cancel($stockCount);

        return redirect()->route('stock-counts.index')->with('success', "{$stockCount->count_no} cancelled.");
    }

    public function sheet(StockCount $stockCount): View
    {
        $this->ensureBranchAccess($stockCount->branch_id);
        $items = $stockCount->items()->with(['product:id,name,sku,barcode,unit_id', 'product.unit:id,short_name'])
            ->join('products', 'products.id', '=', 'stock_count_items.product_id')->orderBy('products.name')->select('stock_count_items.*')->get();

        return view('stock-counts.sheet', ['count' => $stockCount->load(['branch', 'category', 'creator']), 'items' => $items]);
    }
}
