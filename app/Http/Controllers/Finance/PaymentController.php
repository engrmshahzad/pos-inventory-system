<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Supplier;
use App\Services\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $request->filled('from') ? $request->date('from') : today()->subDays(29);
        $to = $request->filled('to') ? $request->date('to') : today();

        $query = Payment::visibleTo($user)
            ->whereBetween('paid_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($user->isOwner() && $request->filled('branch'), fn (Builder $q) => $q->where('branch_id', $request->integer('branch')))
            ->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->input('type')))
            ->when($request->filled('method'), fn (Builder $q) => $q->where('method', $request->input('method')))
            ->when($request->input('direction') === 'in', fn (Builder $q) => $q->whereIn('type', Payment::TYPES_IN))
            ->when($request->input('direction') === 'out', fn (Builder $q) => $q->whereIn('type', Payment::TYPES_OUT))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $t = $request->input('search');
                $q->where(fn ($q) => $q->where('payment_no', 'like', "%{$t}%")->orWhere('reference', 'like', "%{$t}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$t}%"))
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$t}%")));
            });

        $in = "'".implode("','", Payment::TYPES_IN)."'";
        $summary = (clone $query)->selectRaw("COUNT(*) as count,
            COALESCE(SUM(CASE WHEN type IN ({$in}) THEN amount ELSE 0 END),0) as received,
            COALESCE(SUM(CASE WHEN type NOT IN ({$in}) THEN amount ELSE 0 END),0) as paid")->first();

        return view('payments.index', [
            'payments' => $query->with(['customer:id,name', 'supplier:id,name', 'branch:id,code', 'user:id,name', 'payable'])->withCount('allocations')
                ->latest('paid_at')->latest('id')
                ->paginate(in_array((int) $request->input('per_page'), [10, 25, 50, 100], true) ? (int) $request->input('per_page') : 25)->withQueryString(),
            'summary' => $summary,
            'from' => $from,
            'to' => $to,
            'branches' => $user->isOwner() ? Branch::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    /** ?type=receive (from customer) or ?type=pay (to supplier) */
    public function create(Request $request): View
    {
        abort_unless($request->user()->can('payments.create'), 403);
        $type = $request->input('type') === 'pay' ? 'pay' : 'receive';

        return view('payments.create', [
            'type' => $type,
            'party' => $type === 'receive'
                ? ($request->filled('customer') ? Customer::find($request->integer('customer')) : null)
                : ($request->filled('supplier') ? Supplier::find($request->integer('supplier')) : null),
            'methods' => Payment::METHODS,
        ]);
    }

    public function store(Request $request, PaymentService $service): RedirectResponse
    {
        abort_unless($request->user()->can('payments.create'), 403);
        $branch = $this->workingBranch();

        $data = $request->validate([
            'type' => ['required', Rule::in(['receive', 'pay'])],
            'party_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
            'allocation_mode' => ['required', Rule::in(['auto', 'manual'])],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0'],
        ], ['party_id.required' => $request->input('type') === 'pay' ? 'Choose a supplier.' : 'Choose a customer.']);

        $allocations = $data['allocation_mode'] === 'manual' ? ($data['allocations'] ?? []) : null;

        $payment = $data['type'] === 'receive'
            ? $service->receiveFromCustomer($request->user(), $branch, Customer::findOrFail($data['party_id']), $data, $allocations)
            : $service->payToSupplier($request->user(), $branch, Supplier::findOrFail($data['party_id']), $data, $allocations);

        return redirect()->route('payments.show', $payment)->with('success', "{$payment->payment_no} saved: ".money($payment->amount, true).'.');
    }

    public function show(Payment $payment): View
    {
        $this->ensureBranchAccess($payment->branch_id);

        return view('payments.show', ['payment' => $payment->load(['customer', 'supplier', 'branch', 'user', 'payable', 'allocations.allocatable'])]);
    }

    /** Printable receipt / voucher (80mm). */
    public function receipt(Payment $payment): View
    {
        $this->ensureBranchAccess($payment->branch_id);

        return view('payments.receipt', ['payment' => $payment->load(['customer', 'supplier', 'branch', 'user', 'payable', 'allocations.allocatable'])]);
    }
}
