<?php

namespace App\Http\Controllers\Parties;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parties\SupplierRequest;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\LedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $suppliers = Supplier::query()
            ->search($request->input('search'))
            ->when($request->input('filter') === 'payable', fn ($q) => $q->where('balance', '>', 0))
            ->when($request->input('filter') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withSum(['purchases as purchases_total' => fn ($q) => $q->where('status', 'received')], 'grand_total')
            ->withMax('purchases as last_purchase', 'purchase_date')
            ->when($request->input('sort') === 'payable', fn ($q) => $q->orderByDesc('balance'), fn ($q) => $q->orderBy('name'))
            ->paginate(in_array((int) $request->input('per_page'), [10, 25, 50, 100], true) ? (int) $request->input('per_page') : 25)
            ->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'stats' => [
                'total' => Supplier::count(),
                'active' => Supplier::active()->count(),
                'payable' => (float) Supplier::where('balance', '>', 0)->sum('balance'),
                'with_payable' => Supplier::where('balance', '>', 0)->count(),
            ],
        ]);
    }

    public function store(SupplierRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['opening_balance'] = (float) ($data['opening_balance'] ?? 0);
        $supplier = Supplier::create($data + ['balance' => $data['opening_balance']]);

        return redirect()->route('suppliers.show', $supplier)->with('success', "Supplier \"{$supplier->name}\" added.");
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return back()->with('success', "Supplier \"{$supplier->name}\" updated.");
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        abort_unless(auth()->user()->can('suppliers.delete'), 403);

        if ($supplier->purchases()->exists()) {
            throw ValidationException::withMessages(['action' => "{$supplier->name} has purchases. Deactivate the supplier instead."]);
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', "Supplier \"{$supplier->name}\" deleted.");
    }

    public function show(Request $request, Supplier $supplier, LedgerService $ledger): View
    {
        $user = $request->user();
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;

        $purchases = Purchase::where('supplier_id', $supplier->id)
            ->when(! $user->isOwner(), fn ($q) => $q->where('branch_id', $user->branch_id));

        return view('suppliers.show', [
            'supplier' => $supplier,
            'tab' => $request->input('tab', 'overview'),
            'stats' => [
                'purchases' => (float) (clone $purchases)->where('status', 'received')->sum('grand_total'),
                'count' => (clone $purchases)->count(),
                'paid' => (float) Payment::where('supplier_id', $supplier->id)->whereIn('type', ['purchase_payment', 'supplier_payment'])->sum('amount'),
                'last' => (clone $purchases)->max('purchase_date'),
            ],
            'purchases' => (clone $purchases)->with(['branch:id,code'])->withCount('items')->latest('purchase_date')->latest('id')->paginate(10, ['*'], 'purchases_page')->withQueryString(),
            'payments' => Payment::where('supplier_id', $supplier->id)->with(['branch:id,code', 'user:id,name', 'payable'])->withCount('allocations')->latest('paid_at')->paginate(10, ['*'], 'payments_page')->withQueryString(),
            'ledger' => $ledger->supplier($supplier, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function statement(Request $request, Supplier $supplier, LedgerService $ledger): Response
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $request->filled('from') ? $request->date('from') : null;
        $to = $request->filled('to') ? $request->date('to') : null;

        return Pdf::loadView('parties.statement-pdf', [
            'party' => $supplier,
            'kind' => 'supplier',
            'ledger' => $ledger->supplier($supplier, $from, $to),
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4')->stream('statement-'.str($supplier->name)->slug().'.pdf');
    }

    public function search(Request $request): JsonResponse
    {
        return response()->json(Supplier::active()->search($request->input('q'))->orderBy('name')->limit(20)->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'company' => $s->company, 'phone' => $s->phone, 'balance' => (float) $s->balance, 'balance_label' => money($s->balance)]));
    }

    public function openInvoices(Supplier $supplier): JsonResponse
    {
        return response()->json(Purchase::received()->where('supplier_id', $supplier->id)->where('due_amount', '>', 0)
            ->with('branch:id,code')->orderBy('purchase_date')->get()
            ->map(fn ($p) => ['id' => $p->id, 'number' => $p->purchase_no, 'date' => $p->purchase_date->format('d M Y'), 'branch' => $p->branch->code,
                'total' => (float) $p->grand_total, 'due' => (float) $p->due_amount]));
    }
}
