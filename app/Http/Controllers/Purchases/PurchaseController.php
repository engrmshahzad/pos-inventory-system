<?php

namespace App\Http\Controllers\Purchases;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchases\PurchaseRequest;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $service)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $request->filled('from') ? $request->date('from') : today()->subDays(29);
        $to = $request->filled('to') ? $request->date('to') : today();

        $query = Purchase::visibleTo($user)
            ->whereBetween('purchase_date', [$from->toDateString(), $to->toDateString()])
            ->when($user->isOwner() && $request->filled('branch'), fn (Builder $q) => $q->where('branch_id', $request->integer('branch')))
            ->when($request->filled('supplier'), fn (Builder $q) => $q->where('supplier_id', $request->integer('supplier')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->input('status')))
            ->when($request->filled('payment'), fn (Builder $q) => $q->where('payment_status', $request->input('payment')))
            ->when($request->filled('search'), fn (Builder $q) => $q->where(fn ($q) => $q->where('purchase_no', 'like', '%'.$request->input('search').'%')
                ->orWhere('reference_no', 'like', '%'.$request->input('search').'%')));

        $summary = (clone $query)->selectRaw("COUNT(*) as count,
            COALESCE(SUM(CASE WHEN status = 'received' THEN grand_total ELSE 0 END),0) as received,
            COALESCE(SUM(paid_amount),0) as paid,
            COALESCE(SUM(CASE WHEN status = 'received' THEN due_amount ELSE 0 END),0) as due,
            COALESCE(SUM(CASE WHEN status = 'ordered' THEN 1 ELSE 0 END),0) as ordered")->first();

        return view('purchases.index', [
            'purchases' => $query->with(['supplier:id,name,company', 'branch:id,code'])->withCount('items')
                ->latest('purchase_date')->latest('id')
                ->paginate(in_array((int) $request->input('per_page'), [10, 25, 50, 100], true) ? (int) $request->input('per_page') : 25)->withQueryString(),
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'suppliers' => Supplier::orderBy('name')->pluck('name', 'id'),
            'branches' => $user->isOwner() ? Branch::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $supplier = $request->filled('supplier') ? Supplier::find($request->integer('supplier')) : null;

        return view('purchases.create', [
            'branches' => $user->isOwner() ? Branch::active()->orderBy('name')->get(['id', 'name', 'code']) : collect([$user->branch]),
            'defaultBranch' => $user->isOwner() ? currentBranchId() : $user->branch_id,
            'supplier' => $supplier,
            'methods' => Payment::METHODS,
        ]);
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        $branch = Branch::findOrFail($request->validated('branch_id'));
        $purchase = $this->service->create($request->user(), $branch, $request->validated());

        $message = $purchase->status === Purchase::STATUS_RECEIVED
            ? "Purchase {$purchase->purchase_no} saved and stock added to {$branch->name}."
            : "Purchase order {$purchase->purchase_no} saved. Mark it as received when the goods arrive.";

        return redirect()->route('purchases.show', $purchase)->with('success', $message);
    }

    public function show(Purchase $purchase, \App\Services\PurchaseReturnService $returns): View
    {
        $this->ensureBranchAccess($purchase->branch_id);
        $purchase->load(['items.product.unit', 'supplier', 'branch', 'user', 'payments.user', 'allocations.payment', 'returns']);

        return view('purchases.show', [
            'purchase' => $purchase,
            'lines' => $purchase->status === Purchase::STATUS_RECEIVED ? $returns->returnableLines($purchase) : collect(),
        ]);
    }

    public function pdf(Purchase $purchase): Response
    {
        $this->ensureBranchAccess($purchase->branch_id);
        $purchase->load(['items', 'supplier', 'branch', 'user']);

        return Pdf::loadView('purchases.pdf', ['purchase' => $purchase])->setPaper('a4')->stream("{$purchase->purchase_no}.pdf");
    }

    public function receive(Purchase $purchase): RedirectResponse
    {
        abort_unless(auth()->user()->can('purchases.edit'), 403);
        $this->ensureBranchAccess($purchase->branch_id);

        $this->service->receive($purchase);

        return back()->with('success', "{$purchase->purchase_no} received. Stock added.");
    }

    public function destroy(Purchase $purchase): RedirectResponse
    {
        abort_unless(auth()->user()->can('purchases.edit'), 403);
        $this->ensureBranchAccess($purchase->branch_id);

        $this->service->delete($purchase);

        return redirect()->route('purchases.index')->with('success', "Order {$purchase->purchase_no} deleted.");
    }
}
