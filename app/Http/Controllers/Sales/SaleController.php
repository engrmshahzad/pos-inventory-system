<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Services\BarcodeService;
use App\Services\SaleService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date']]);

        // Cashiers start on today's sales; everyone else on this month.
        $from = $request->filled('from') ? $request->date('from') : ($user->isCashier() ? today() : today()->startOfMonth());
        $to = $request->filled('to') ? $request->date('to') : today();

        $query = Sale::completed()
            ->visibleTo($user)
            ->whereBetween('sold_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->when($user->isOwner() && $request->filled('branch'), fn (Builder $q) => $q->where('sales.branch_id', $request->integer('branch')))
            ->when($request->filled('cashier') && ! $user->isCashier(), fn (Builder $q) => $q->where('sales.user_id', $request->integer('cashier')))
            ->when($request->filled('status'), fn (Builder $q) => $q->where('payment_status', $request->input('status')))
            ->when($request->filled('method'), fn (Builder $q) => $q->whereHas('payments', fn ($p) => $p->where('method', $request->input('method'))))
            ->when($request->filled('search'), function (Builder $q) use ($request) {
                $term = $request->input('search');
                $q->where(fn ($q) => $q->where('invoice_no', 'like', "%{$term}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")));
            });

        $summary = (clone $query)->selectRaw('COUNT(*) as count, COALESCE(SUM(grand_total),0) as total, COALESCE(SUM(paid_amount),0) as paid, COALESCE(SUM(due_amount),0) as due')->first();

        $profit = null;
        if ($user->can('reports.profit')) {
            // Revenue excluding tax and shipping, minus cost snapshot of items sold.
            $ids = (clone $query)->select('sales.id');
            $cost = (float) DB::table('sale_items')->whereIn('sale_id', $ids)->sum(DB::raw('purchase_price * quantity'));
            $net = (float) (clone $query)->sum(DB::raw('grand_total - tax_amount - shipping'));
            $profit = round($net - $cost, 2);
        }

        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true) ? (int) $request->input('per_page') : 25;
        $sales = $query->with(['customer:id,name,phone', 'user:id,name', 'branch:id,name,code'])
            ->withCount('items')
            ->latest('sold_at')
            ->paginate($perPage)
            ->withQueryString();

        return view('sales.index', [
            'sales' => $sales,
            'summary' => $summary,
            'profit' => $profit,
            'from' => $from,
            'to' => $to,
            'branches' => $user->isOwner() ? Branch::orderBy('name')->get(['id', 'name', 'code']) : collect(),
            'cashiers' => $user->isCashier() ? collect() : User::query()
                ->when(! $user->isOwner(), fn ($q) => $q->where('branch_id', $user->branch_id))
                ->when($user->isOwner() && currentBranchId(), fn ($q) => $q->where('branch_id', currentBranchId()))
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['cashier', 'manager', 'owner']))
                ->orderBy('name')->pluck('name', 'id'),
        ]);
    }

    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);
        abort_if($sale->status !== Sale::STATUS_COMPLETED, 404);

        $sale->load(['items.product.unit', 'payments.user', 'customer', 'branch', 'user']);

        return view('sales.show', ['sale' => $sale]);
    }

    /** Thermal receipt (58 / 80 mm), opens the print dialog. */
    public function receipt(Sale $sale, BarcodeService $barcodes): View
    {
        $this->authorize('view', $sale);
        abort_if($sale->status !== Sale::STATUS_COMPLETED, 404);

        return view('sales.receipt', [
            'sale' => $sale->load(['items', 'payments', 'customer', 'branch', 'user']),
            'width' => settings('receipt_size', '80') === '58' ? 58 : 80,
            'barcode' => $barcodes->svg($sale->invoice_no, 30),
        ]);
    }

    public function pdf(Sale $sale, BarcodeService $barcodes): Response
    {
        $this->authorize('view', $sale);
        abort_if($sale->status !== Sale::STATUS_COMPLETED, 404);

        $sale->load(['items', 'payments', 'customer', 'branch', 'user']);
        $barcode = 'data:image/svg+xml;base64,'.base64_encode($barcodes->svg($sale->invoice_no, 30));

        return Pdf::loadView('sales.invoice-pdf', ['sale' => $sale, 'barcode' => $barcode])
            ->setPaper('a4')
            ->stream("{$sale->invoice_no}.pdf");
    }

    public function receivePayment(Request $request, Sale $sale, SaleService $service): RedirectResponse
    {
        $this->authorize('receivePayment', $sale);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['required', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $service->receivePayment($sale, $request->user(), (float) $data['amount'], $data['method'], $data['reference'] ?? null, $data['note'] ?? null);

        return back()->with('success', 'Payment of '.money($data['amount'], true).' received.');
    }
}
