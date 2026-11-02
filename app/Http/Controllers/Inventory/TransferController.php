<?php

namespace App\Http\Controllers\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\TransferService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class TransferController extends Controller
{
    public function __construct(protected TransferService $service)
    {
    }

    public function index(Request $request): View
    {
        $user = $request->user();
        $branchId = $user->isOwner() ? currentBranchId() : $user->branch_id;
        $tab = $request->input('tab', 'all');

        $base = StockTransfer::visibleTo($user);
        $pendingQuery = (clone $base)->where('status', 'sent')->when($branchId, fn ($q) => $q->where('to_branch_id', $branchId));

        $query = (clone $base)
            ->when($tab === 'outgoing' && $branchId, fn ($q) => $q->where('from_branch_id', $branchId))
            ->when($tab === 'incoming' && $branchId, fn ($q) => $q->where('to_branch_id', $branchId))
            ->when($tab === 'pending', fn ($q) => $q->where('status', 'sent')->when($branchId, fn ($q) => $q->where('to_branch_id', $branchId)))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->when($request->filled('search'), fn ($q) => $q->where('transfer_no', 'like', '%'.$request->input('search').'%'));

        return view('transfers.index', [
            'transfers' => $query->with(['fromBranch:id,name,code', 'toBranch:id,name,code', 'creator:id,name'])->withCount('items')
                ->latest('id')->paginate(25)->withQueryString(),
            'tab' => $tab,
            'branchId' => $branchId,
            'counts' => [
                'pending' => (clone $pendingQuery)->count(),
                'in_transit' => (clone $base)->where('status', 'sent')->count(),
                'drafts' => (clone $base)->where('status', 'draft')->count(),
                'short' => (clone $base)->where('status', 'partially_received')->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        $user = $request->user();
        $prefill = collect();

        // Pre-filled from "Suggest transfers": ?from=1&to=2&products[5]=12
        if ($request->filled('products')) {
            $qtys = collect((array) $request->input('products'))->map(fn ($q) => (float) $q);
            $prefill = Product::with('unit')->whereIn('id', $qtys->keys())->get()->map(fn ($p) => [
                'id' => $p->id, 'name' => $p->name, 'sku' => $p->sku, 'unit' => $p->unit?->short_name, 'decimal' => (bool) $p->unit?->allow_decimal,
                'qty' => $qtys[$p->id], 'note' => '',
            ])->values();
        }

        return view('transfers.create', [
            'branches' => Branch::active()->orderBy('name')->get(['id', 'name', 'code']),
            'from' => $user->isOwner() ? ($request->integer('from') ?: currentBranchId()) : $user->branch_id,
            'to' => $request->integer('to') ?: null,
            'prefill' => $prefill,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'from_branch_id' => ['nullable', 'integer'],
            'to_branch_id' => ['required', 'integer'],
            'transfer_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.note' => ['nullable', 'string', 'max:100'],
            'action' => ['required', 'in:draft,send'],
        ], ['items.required' => 'Add at least one product.']);

        try {
            $transfer = $this->service->create($request->user(), $data, $data['action'] === 'send');
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['items' => $e->getMessage()]);
        }

        $message = match (true) {
            $transfer->status === 'sent' => "{$transfer->transfer_no} sent to {$transfer->toBranch->name}. They have been notified.",
            $transfer->is_request => "Transfer request {$transfer->transfer_no} created. {$transfer->fromBranch->name} has been notified.",
            default => "Draft {$transfer->transfer_no} saved.",
        };

        return redirect()->route('transfers.show', $transfer)->with('success', $message);
    }

    public function show(StockTransfer $transfer): View
    {
        $this->ensureVisible($transfer);
        $transfer->load(['items.product.unit', 'fromBranch', 'toBranch', 'creator', 'sender', 'receiver', 'canceller']);
        $available = \App\Models\BranchStock::where('branch_id', $transfer->from_branch_id)->whereIn('product_id', $transfer->items->pluck('product_id'))->pluck('quantity', 'product_id');

        return view('transfers.show', ['transfer' => $transfer, 'available' => $available]);
    }

    public function send(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->ensureVisible($transfer);

        try {
            $this->service->send($transfer, $request->user());
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['action' => $e->getMessage()]);
        }

        return back()->with('success', "{$transfer->transfer_no} sent. Stock deducted from {$transfer->fromBranch->name}.");
    }

    public function receive(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->ensureVisible($transfer);
        $data = $request->validate([
            'received' => ['array'],
            'received.*' => ['nullable', 'numeric', 'min:0'],
            'receive_note' => ['nullable', 'string', 'max:255'],
        ]);

        $transfer = $this->service->receive($transfer, $request->user(), $data['received'] ?? [], $data['receive_note'] ?? null);

        return back()->with($transfer->status === 'received' ? 'success' : 'warning',
            $transfer->status === 'received' ? "{$transfer->transfer_no} received in full." : "{$transfer->transfer_no} received with a shortage. The owner and sender have been notified.");
    }

    public function cancel(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $this->ensureVisible($transfer);
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        $wasSent = $transfer->status === 'sent';
        $this->service->cancel($transfer, $request->user(), $data['cancel_reason'] ?? null);

        return back()->with('success', "{$transfer->transfer_no} cancelled.".($wasSent ? ' Stock returned to the sending branch.' : ''));
    }

    public function challan(StockTransfer $transfer): Response
    {
        $this->ensureVisible($transfer);
        $transfer->load(['items.product.unit', 'fromBranch', 'toBranch', 'creator', 'sender', 'receiver']);

        return Pdf::loadView('transfers.challan', ['transfer' => $transfer])->setPaper('a4')->stream("{$transfer->transfer_no}-challan.pdf");
    }

    public function suggestions(): View
    {
        $suggestions = $this->service->suggestions();
        $branches = Branch::pluck('name', 'id');

        return view('transfers.suggestions', [
            'groups' => $suggestions->groupBy(fn ($s) => $s['from_branch_id'].'-'.$s['to_branch_id']),
            'branches' => $branches,
            'codes' => Branch::pluck('code', 'id'),
        ]);
    }

    protected function ensureVisible(StockTransfer $transfer): void
    {
        $user = auth()->user();
        abort_unless($user->isOwner() || in_array($user->branch_id, [$transfer->from_branch_id, $transfer->to_branch_id], true), 403);
    }
}
