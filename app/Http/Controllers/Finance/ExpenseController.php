<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Services\ExpenseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ExpenseController extends Controller
{
    public function __construct(protected ExpenseService $service)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);
        $from = $request->filled('from') ? $request->date('from') : today()->startOfMonth();
        $to = $request->filled('to') ? $request->date('to') : today();

        $query = Expense::visibleTo($user)
            ->whereBetween('expense_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($user->isOwner() && $request->filled('branch'), fn (Builder $q) => $q->where('branch_id', $request->integer('branch')))
            ->when($request->filled('category'), fn (Builder $q) => $q->where('expense_category_id', $request->integer('category')))
            ->when($request->filled('method'), fn (Builder $q) => $q->where('method', $request->input('method')))
            ->when($request->filled('search'), fn (Builder $q) => $q->where(fn ($q) => $q->where('expense_no', 'like', '%'.$request->input('search').'%')
                ->orWhere('paid_to', 'like', '%'.$request->input('search').'%')->orWhere('note', 'like', '%'.$request->input('search').'%')));

        $byCategory = (clone $query)->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.id', 'expense_categories.name', 'expense_categories.color')
            ->orderByDesc(DB::raw('SUM(expenses.amount)'))
            ->toBase() // plain rows: Expense has a `name` accessor that would hide the category name
            ->get(['expense_categories.name', 'expense_categories.color', DB::raw('SUM(expenses.amount) as total')]);

        $monthly = Expense::visibleTo($user)->where('expense_date', '>=', today()->subMonths(5)->startOfMonth())
            ->selectRaw(\App\Support\Sql::month('expense_date').' as ym, SUM(amount) as total')->groupBy('ym')->orderBy('ym')->pluck('total', 'ym');

        return view('expenses.index', [
            'expenses' => (clone $query)->with(['category:id,name,color', 'branch:id,code', 'user:id,name'])
                ->latest('expense_date')->latest('id')->paginate(25)->withQueryString(),
            'total' => (float) (clone $query)->sum('amount'),
            'count' => (clone $query)->count(),
            'thisMonth' => (float) Expense::visibleTo($user)->where('expense_date', '>=', today()->startOfMonth())->sum('amount'),
            'lastMonth' => (float) Expense::visibleTo($user)->whereBetween('expense_date', [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()])->sum('amount'),
            'byCategory' => $byCategory,
            'monthly' => collect(range(5, 0))->mapWithKeys(fn ($m) => [today()->subMonths($m)->format('M Y') => (float) ($monthly[today()->subMonths($m)->format('Y-m')] ?? 0)]),
            'from' => $from,
            'to' => $to,
            'categories' => ExpenseCategory::orderBy('name')->get(),
            'branches' => $user->isOwner() ? Branch::orderBy('name')->get(['id', 'name']) : collect(),
            'methods' => Payment::METHODS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.create'), 403);
        $branch = $this->workingBranch();
        $expense = $this->service->create($request->user(), $branch, $this->validated($request), $request->file('attachment'));

        return redirect()->route('expenses.index')->with('success', "Expense {$expense->expense_no} of ".money($expense->amount, true).' saved.');
    }

    public function update(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.edit'), 403);
        $this->ensureBranchAccess($expense->branch_id);
        $this->service->update($expense, $this->validated($request), $request->file('attachment'), $request->boolean('remove_attachment'));

        return back()->with('success', "Expense {$expense->expense_no} updated.");
    }

    public function destroy(Request $request, Expense $expense): RedirectResponse
    {
        abort_unless($request->user()->can('expenses.delete'), 403);
        $this->ensureBranchAccess($expense->branch_id);
        $expense->delete();

        return back()->with('success', "Expense {$expense->expense_no} deleted.");
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'expense_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'paid_to' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
            'remove_attachment' => ['nullable', 'boolean'],
        ], ['attachment.mimes' => 'Receipt must be an image (JPG, PNG, WEBP) or a PDF.']);
    }
}
