<?php

namespace App\Http\Controllers\Purchases;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseReturn;
use App\Services\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function __construct(protected PurchaseReturnService $service)
    {
    }

    public function index(Request $request): View
    {
        $query = PurchaseReturn::visibleTo($request->user())
            ->when($request->filled('search'), fn ($q) => $q->where(fn ($q) => $q->where('return_no', 'like', '%'.$request->input('search').'%')
                ->orWhereHas('purchase', fn ($p) => $p->where('purchase_no', 'like', '%'.$request->input('search').'%'))));

        return view('purchase-returns.index', [
            'returns' => (clone $query)->with(['purchase:id,purchase_no', 'supplier:id,name', 'branch:id,code', 'user:id,name'])->withCount('items')
                ->latest('returned_at')->paginate(25)->withQueryString(),
            'summary' => (clone $query)->selectRaw('COUNT(*) as count, COALESCE(SUM(total),0) as total')->first(),
            'thisMonth' => (float) (clone $query)->where('returned_at', '>=', today()->startOfMonth())->sum('total'),
        ]);
    }

    /** Pick a purchase (by number) or show its returnable lines. */
    public function create(Request $request): View|RedirectResponse
    {
        $purchase = null;

        if ($request->filled('purchase')) {
            $term = trim($request->input('purchase'));
            $purchase = Purchase::visibleTo($request->user())->received()
                ->where(fn ($q) => $q->where('purchase_no', $term)->orWhere('id', ctype_digit($term) ? (int) $term : 0))->first();

            if (! $purchase) {
                return redirect()->route('purchase-returns.create')->withErrors(['purchase' => "No received purchase \"{$term}\" found in your branch."])->withInput();
            }
        }

        return view('purchase-returns.create', [
            'purchase' => $purchase?->load(['supplier', 'branch']),
            'lines' => $purchase ? $this->service->returnableLines($purchase) : collect(),
            'methods' => Payment::METHODS,
            'recent' => Purchase::visibleTo($request->user())->received()->with('supplier:id,name')->latest('received_at')->limit(8)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'integer'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable', 'numeric', 'min:0'],
            'settle_method' => ['required', Rule::in(array_merge(['adjust'], array_keys(Payment::METHODS)))],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $purchase = Purchase::findOrFail($data['purchase_id']);
        $this->ensureBranchAccess($purchase->branch_id);

        try {
            $return = $this->service->create($request->user(), $purchase, $data['items'], $data['settle_method'], $data['reason'] ?? null);
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage().' Items already sold cannot be returned to the supplier.']);
        }

        return redirect()->route('purchase-returns.show', $return)->with('success', "Purchase return {$return->return_no} saved.");
    }

    public function show(PurchaseReturn $purchaseReturn): View
    {
        $this->ensureBranchAccess($purchaseReturn->branch_id);

        return view('purchase-returns.show', ['return' => $purchaseReturn->load(['items.product.unit', 'purchase', 'supplier', 'branch', 'user', 'payments'])]);
    }
}
