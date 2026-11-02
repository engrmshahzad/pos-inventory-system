<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Services\AdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdjustmentController extends Controller
{
    public function __construct(protected AdjustmentService $service)
    {
    }

    public function index(Request $request): View
    {
        $base = StockAdjustment::visibleTo($request->user());
        $status = $request->input('status');

        return view('adjustments.index', [
            'adjustments' => (clone $base)
                ->when($status, fn ($q) => $q->where('status', $status))
                ->when($request->filled('reason'), fn ($q) => $q->where('reason', $request->input('reason')))
                ->with(['branch:id,code', 'creator:id,name'])->withCount('items')
                ->latest('id')->paginate(25)->withQueryString(),
            'status' => $status,
            'counts' => [
                'pending' => (clone $base)->where('status', 'pending')->count(),
                'loss' => (float) (clone $base)->where('status', 'approved')->where('total_value', '<', 0)->sum('total_value'),
                'gain' => (float) (clone $base)->where('status', 'approved')->where('total_value', '>', 0)->sum('total_value'),
                'month' => (clone $base)->where('adjustment_date', '>=', today()->startOfMonth())->count(),
            ],
            'threshold' => $this->service->threshold(),
        ]);
    }

    public function create(Request $request): View
    {
        $prefill = collect();
        if ($request->filled('products')) {
            $qtys = collect((array) $request->input('products'))->map(fn ($q) => (float) $q);
            $branchId = currentBranchId();
            $prefill = Product::with('unit')->withStock($branchId)->whereIn('id', $qtys->keys())->get()->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit?->short_name, 'decimal' => (bool) $p->unit?->allow_decimal,
                'cost' => (float) $p->purchase_price, 'stock' => (float) $p->stock_qty, 'qty' => $qtys[$p->id], 'note' => $request->input('note_'.$p->id, ''),
            ])->values();
        }

        return view('adjustments.create', [
            'reasons' => StockAdjustment::REASONS,
            'reason' => $request->input('reason', 'damaged'),
            'prefill' => $prefill,
            'threshold' => $this->service->threshold(),
            'needsApproval' => ! $request->user()->isOwner(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $branch = $this->workingBranch();
        $data = $request->validate([
            'adjustment_date' => ['required', 'date', 'before_or_equal:today'],
            'reason' => ['required', Rule::in(array_keys(StockAdjustment::REASONS))],
            'note' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(['manual', 'expiry'])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'not_in:0'],
            'items.*.note' => ['nullable', 'string', 'max:100'],
        ], ['items.required' => 'Add at least one product.', 'items.*.quantity.not_in' => 'Quantity cannot be 0.']);

        try {
            $adjustment = $this->service->create($request->user(), $branch, $data);
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        return redirect()->route('adjustments.show', $adjustment)->with(
            $adjustment->status === 'pending' ? 'warning' : 'success',
            $adjustment->status === 'pending'
                ? "{$adjustment->adjustment_no} is above ".money($this->service->threshold()).' and is waiting for owner approval. Stock will change once approved.'
                : "{$adjustment->adjustment_no} applied. Stock updated."
        );
    }

    public function show(StockAdjustment $adjustment): View
    {
        $this->ensureBranchAccess($adjustment->branch_id);
        $adjustment->load(['items.product.unit', 'branch', 'creator', 'approver']);
        $stock = \App\Models\BranchStock::where('branch_id', $adjustment->branch_id)->whereIn('product_id', $adjustment->items->pluck('product_id'))->pluck('quantity', 'product_id');

        return view('adjustments.show', ['adjustment' => $adjustment, 'stock' => $stock]);
    }

    public function approve(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        try {
            $this->service->approve($adjustment, $request->user());
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('success', "{$adjustment->adjustment_no} approved. Stock updated.");
    }

    public function reject(Request $request, StockAdjustment $adjustment): RedirectResponse
    {
        $data = $request->validate(['rejection_reason' => ['nullable', 'string', 'max:255']]);
        $this->service->reject($adjustment, $request->user(), $data['rejection_reason'] ?? null);

        return back()->with('success', "{$adjustment->adjustment_no} rejected. Stock was not changed.");
    }
}
