<?php

namespace App\Http\Controllers\Parties;

use App\Http\Controllers\Controller;
use App\Http\Requests\Parties\CustomerRequest;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\LedgerService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true) ? (int) $request->input('per_page') : 25;

        $customers = Customer::query()
            ->search($request->input('search'))
            ->when($request->input('filter') === 'due', fn ($q) => $q->where('balance', '>', 0))
            ->when($request->input('filter') === 'over_limit', fn ($q) => $q->where('credit_limit', '>', 0)->whereColumn('balance', '>', 'credit_limit'))
            ->when($request->input('filter') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->filled('city'), fn ($q) => $q->where('city', $request->input('city')))
            ->withMax(['sales as last_visit' => fn ($q) => $q->where('status', 'completed')], 'sold_at')
            ->when($request->input('sort') === 'due', fn ($q) => $q->orderByDesc('balance'), fn ($q) => $q->orderBy('name'))
            ->paginate($perPage)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'cities' => Customer::whereNotNull('city')->distinct()->orderBy('city')->pluck('city'),
            'stats' => [
                'total' => Customer::count(),
                'with_due' => Customer::where('balance', '>', 0)->count(),
                'receivable' => (float) Customer::where('balance', '>', 0)->sum('balance'),
                'over_limit' => Customer::where('credit_limit', '>', 0)->whereColumn('balance', '>', 'credit_limit')->count(),
            ],
        ]);
    }

    public function store(CustomerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['opening_balance'] = (float) ($data['opening_balance'] ?? 0);
        $data['credit_limit'] = (float) ($data['credit_limit'] ?? 0);
        $customer = Customer::create($data + ['balance' => $data['opening_balance']]);

        return redirect()->route('customers.show', $customer)->with('success', "Customer \"{$customer->name}\" added.");
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $data = $request->validated();
        $data['credit_limit'] = (float) ($data['credit_limit'] ?? 0);
        $customer->update($data);

        return back()->with('success', "Customer \"{$customer->name}\" updated.");
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        abort_unless(auth()->user()->can('customers.delete'), 403);

        if ((float) $customer->balance != 0) {
            throw ValidationException::withMessages(['action' => "{$customer->name} has a balance of ".money($customer->balance, true).'. Settle it before deleting.']);
        }

        $customer->delete();

        return redirect()->route('customers.index')->with('success', "Customer \"{$customer->name}\" deleted.");
    }

    public function show(Request $request, Customer $customer, LedgerService $ledger): View
    {
        $user = $request->user();
        [$from, $to] = $this->range($request);

        // The khata is shared by all branches, so totals and history cover every branch.
        $salesQuery = Sale::completed()->where('customer_id', $customer->id);

        $stats = (clone $salesQuery)->selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total),0) as total, MAX(sold_at) as last_visit')->first();

        return view('customers.show', [
            'customer' => $customer,
            'tab' => $request->input('tab', 'sales'),
            'stats' => [
                'count' => (int) $stats->count,
                'total' => (float) $stats->total,
                'paid' => (float) Payment::where('customer_id', $customer->id)->whereIn('type', ['sale', 'customer_receipt'])->sum('amount'),
                'last_visit' => $stats->last_visit ? \Illuminate\Support\Carbon::parse($stats->last_visit) : null,
            ],
            'canOpenBranch' => fn (int $branchId) => $user->isOwner() || $branchId === $user->branch_id,
            'sales' => (clone $salesQuery)->with(['branch:id,code', 'user:id,name'])->withCount('items')->latest('sold_at')->paginate(10, ['*'], 'sales_page')->withQueryString(),
            'payments' => Payment::where('customer_id', $customer->id)->with(['branch:id,code', 'user:id,name', 'payable'])->withCount('allocations')->latest('paid_at')->paginate(10, ['*'], 'payments_page')->withQueryString(),
            'ledger' => $ledger->customer($customer, $from, $to),
            'from' => $from,
            'to' => $to,
        ]);
    }

    public function statement(Request $request, Customer $customer, LedgerService $ledger): Response
    {
        [$from, $to] = $this->range($request);

        return Pdf::loadView('parties.statement-pdf', [
            'party' => $customer,
            'kind' => 'customer',
            'ledger' => $ledger->customer($customer, $from, $to),
            'from' => $from,
            'to' => $to,
        ])->setPaper('a4')->stream('statement-'.str($customer->name)->slug().'.pdf');
    }

    /** JSON for Tom Select on the payments form. */
    public function search(Request $request): JsonResponse
    {
        return response()->json(Customer::active()->search($request->input('q'))->orderBy('name')->limit(20)->get()
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'phone' => $c->phone, 'balance' => (float) $c->balance, 'balance_label' => money($c->balance)]));
    }

    /** Unpaid invoices for manual allocation. */
    public function openInvoices(Customer $customer): JsonResponse
    {
        return response()->json(Sale::completed()->where('customer_id', $customer->id)->where('due_amount', '>', 0)
            ->with('branch:id,code')->orderBy('sold_at')->get()
            ->map(fn ($s) => ['id' => $s->id, 'number' => $s->invoice_no, 'date' => $s->sold_at->format('d M Y'), 'branch' => $s->branch->code,
                'total' => (float) $s->grand_total, 'due' => (float) $s->due_amount]));
    }

    protected function range(Request $request): array
    {
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        return [
            $request->filled('from') ? $request->date('from') : null,
            $request->filled('to') ? $request->date('to') : null,
        ];
    }
}
