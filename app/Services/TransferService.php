<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Two-step transfer: send (FROM stock out) → receive (TO stock in, shortages recorded).
 */
class TransferService
{
    public function __construct(
        protected StockService $stock,
        protected InvoiceNumberService $numbers,
        protected NotifyService $notify,
    ) {
    }

    public function create(User $user, array $data, bool $sendNow = false): StockTransfer
    {
        $from = Branch::active()->find($user->isOwner() ? ($data['from_branch_id'] ?? null) : $user->branch_id)
            ?? throw ValidationException::withMessages(['from_branch_id' => 'Choose an active branch to send from.']);
        $to = Branch::active()->find($data['to_branch_id'] ?? null)
            ?? throw ValidationException::withMessages(['to_branch_id' => 'Choose an active branch to send to.']);

        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_branch_id' => 'The receiving branch must be different from the sending branch.']);
        }

        $lines = $this->lines($data['items'] ?? [], $from);

        return DB::transaction(function () use ($user, $data, $from, $to, $lines, $sendNow) {
            $transfer = StockTransfer::create([
                'transfer_no' => $this->numbers->next('TR'),
                'from_branch_id' => $from->id,
                'to_branch_id' => $to->id,
                'status' => 'draft',
                'is_request' => $user->isOwner() && ! $sendNow,
                'transfer_date' => $data['transfer_date'] ?? today(),
                'note' => $data['note'] ?? null,
                'created_by' => $user->id,
            ]);
            $transfer->items()->createMany($lines);

            activity('created', "Transfer {$transfer->transfer_no} {$from->code} → {$to->code} created", $transfer);

            if ($sendNow) {
                return $this->send($transfer, $user);
            }

            if ($transfer->is_request) {
                $this->notify->send($this->notify->branchManagers($from->id), new AppNotification(
                    'Transfer requested',
                    "Please send {$transfer->transfer_no} ({$transfer->items->count()} items) to {$to->name}.",
                    route('transfers.show', $transfer), 'arrow-left-right', 'warning', 'transfer_request',
                ));
            }

            return $transfer;
        });
    }

    public function send(StockTransfer $transfer, User $user): StockTransfer
    {
        $this->ensureCan($user, $transfer->from_branch_id, 'send');

        return DB::transaction(function () use ($transfer, $user) {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->with(['items', 'fromBranch', 'toBranch'])->first();

            if ($transfer->status !== 'draft') {
                throw ValidationException::withMessages(['action' => 'Only draft transfers can be sent.']);
            }

            foreach ($transfer->items as $item) {
                $cost = (float) Product::withTrashed()->whereKey($item->product_id)->value('purchase_price');
                // Throws InsufficientStockException → whole send rolls back.
                $this->stock->decrease($transfer->from_branch_id, $item->product_id, (float) $item->quantity, 'transfer_out', $transfer, $transfer->transfer_no, "To {$transfer->toBranch->name}", $cost);
                $item->update(['unit_cost' => $cost]);
            }

            $transfer->update(['status' => 'sent', 'sent_by' => $user->id, 'sent_at' => now()]);
            activity('sent', "Transfer {$transfer->transfer_no} sent to {$transfer->toBranch->name}", $transfer);

            $this->notify->send($this->notify->branchManagers($transfer->to_branch_id), new AppNotification(
                'Incoming transfer',
                "{$transfer->transfer_no} from {$transfer->fromBranch->name} is on its way ({$transfer->items->count()} items).",
                route('transfers.show', $transfer), 'truck', 'info', 'transfer_incoming',
            ));

            return $transfer;
        });
    }

    /**
     * @param  array<int, float|string|null>  $received  [item_id => received qty]; missing = full qty
     */
    public function receive(StockTransfer $transfer, User $user, array $received, ?string $note = null): StockTransfer
    {
        $this->ensureCan($user, $transfer->to_branch_id, 'receive');

        return DB::transaction(function () use ($transfer, $user, $received, $note) {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->with(['items.product.unit', 'fromBranch', 'toBranch'])->first();

            if ($transfer->status !== 'sent') {
                throw ValidationException::withMessages(['action' => 'Only transfers in transit can be received.']);
            }

            $short = false;
            foreach ($transfer->items as $item) {
                $sent = (float) $item->quantity;
                $qty = array_key_exists($item->id, $received) && $received[$item->id] !== null && $received[$item->id] !== ''
                    ? round((float) $received[$item->id], 3) : $sent;

                if ($qty < 0 || $qty > $sent) {
                    throw ValidationException::withMessages(['received' => "{$item->product?->name}: received quantity must be between 0 and ".rtrim(rtrim(number_format($sent, 3), '0'), '.').'.']);
                }
                if (! $item->product?->unit?->allow_decimal && floor($qty) != $qty) {
                    throw ValidationException::withMessages(['received' => "{$item->product?->name}: quantity must be a whole number."]);
                }

                if ($qty > 0) {
                    $this->stock->increase($transfer->to_branch_id, $item->product_id, $qty, 'transfer_in', $transfer, $transfer->transfer_no, "From {$transfer->fromBranch->name}", (float) $item->unit_cost);
                }

                $shortage = round($sent - $qty, 3);
                $short = $short || $shortage > 0;
                $item->update(['received_quantity' => $qty, 'shortage' => $shortage]);
            }

            $transfer->update([
                'status' => $short ? 'partially_received' : 'received',
                'received_by' => $user->id,
                'received_at' => now(),
                'receive_note' => $note,
            ]);
            activity('received', "Transfer {$transfer->transfer_no} received".($short ? ' with shortage' : ''), $transfer);

            $this->notify->send($this->notify->branchManagers($transfer->from_branch_id, withOwners: $short), new AppNotification(
                $short ? 'Transfer received with shortage' : 'Transfer received',
                "{$transfer->toBranch->name} received {$transfer->transfer_no}".($short ? ' — some items were short.' : ' in full.'),
                route('transfers.show', $transfer), $short ? 'alert-triangle' : 'check-circle', $short ? 'danger' : 'success', 'transfer_received',
            ));

            return $transfer;
        });
    }

    /** Draft: creator, sending branch or owner. Sent: owner only (stock goes back to the sending branch). */
    public function cancel(StockTransfer $transfer, User $user, ?string $reason = null): StockTransfer
    {
        return DB::transaction(function () use ($transfer, $user, $reason) {
            $transfer = StockTransfer::whereKey($transfer->id)->lockForUpdate()->with(['items', 'fromBranch'])->first();

            if ($transfer->status === 'draft') {
                $allowed = $user->isOwner() || $transfer->created_by === $user->id || $transfer->from_branch_id === $user->branch_id;
                abort_unless($allowed, 403);
            } elseif ($transfer->status === 'sent') {
                if (! ($user->isOwner() && $user->can('transfers.approve'))) {
                    throw ValidationException::withMessages(['action' => 'Only the owner can cancel a transfer that is already in transit.']);
                }
                foreach ($transfer->items as $item) {
                    $this->stock->increase($transfer->from_branch_id, $item->product_id, (float) $item->quantity, 'transfer_in', $transfer, $transfer->transfer_no, 'Transfer cancelled — returned', (float) $item->unit_cost);
                }
            } else {
                throw ValidationException::withMessages(['action' => 'This transfer can no longer be cancelled.']);
            }

            $transfer->update(['status' => 'cancelled', 'cancelled_by' => $user->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);
            activity('cancelled', "Transfer {$transfer->transfer_no} cancelled", $transfer);

            return $transfer;
        });
    }

    /** Products low in one branch but plentiful in another (keeps 2× min stock at the donor). */
    public function suggestions(int $limit = 50): \Illuminate\Support\Collection
    {
        $rows = DB::table('branch_stock')
            ->join('products', 'products.id', '=', 'branch_stock.product_id')
            ->join('branches', 'branches.id', '=', 'branch_stock.branch_id')
            ->whereNull('products.deleted_at')->where('products.is_active', true)->where('products.min_stock', '>', 0)
            ->whereNull('branches.deleted_at')->where('branches.is_active', true)
            ->get(['branch_stock.branch_id', 'branch_stock.product_id', 'branch_stock.quantity', 'products.min_stock', 'products.name', 'products.sku']);

        $suggestions = collect();
        foreach ($rows->groupBy('product_id') as $productRows) {
            $min = (float) $productRows->first()->min_stock;
            // Spare stock per donor branch; reduced as it is promised so it is never suggested twice.
            $spare = $productRows->filter(fn ($r) => (float) $r->quantity > 2 * $min)
                ->mapWithKeys(fn ($r) => [$r->branch_id => floor((float) $r->quantity - 2 * $min)]);

            foreach ($productRows->filter(fn ($r) => (float) $r->quantity <= $min)->sortBy('quantity') as $low) {
                $donorId = $spare->except($low->branch_id)->filter(fn ($q) => $q > 0)->sortDesc()->keys()->first();
                if ($donorId === null) {
                    continue;
                }
                $qty = min(ceil(2 * $min - (float) $low->quantity), $spare[$donorId]);
                if ($qty <= 0) {
                    continue;
                }
                $spare[$donorId] -= $qty;
                $suggestions->push([
                    'product_id' => $low->product_id, 'name' => $low->name, 'sku' => $low->sku, 'min' => $min,
                    'to_branch_id' => $low->branch_id, 'to_qty' => (float) $low->quantity,
                    'from_branch_id' => $donorId, 'from_qty' => (float) $productRows->firstWhere('branch_id', $donorId)->quantity,
                    'suggested' => $qty,
                ]);
            }
        }

        return $suggestions->sortBy('to_qty')->take($limit)->values();
    }

    protected function lines(array $items, Branch $from): array
    {
        $merged = collect($items)->filter(fn ($i) => ! empty($i['product_id']))
            ->groupBy('product_id')
            ->map(fn ($group, $id) => ['product_id' => (int) $id, 'quantity' => round($group->sum(fn ($i) => (float) ($i['quantity'] ?? 0)), 3), 'note' => $group->pluck('note')->filter()->first()]);

        if ($merged->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one product.']);
        }

        $products = Product::with('unit')->whereIn('id', $merged->keys())->get()->keyBy('id');
        $available = DB::table('branch_stock')->where('branch_id', $from->id)->whereIn('product_id', $merged->keys())->pluck('quantity', 'product_id');

        return $merged->map(function ($line) use ($products, $available, $from) {
            $product = $products->get($line['product_id']) ?? throw ValidationException::withMessages(['items' => 'A selected product no longer exists.']);
            if ($line['quantity'] <= 0) {
                throw ValidationException::withMessages(['items' => "{$product->name}: quantity must be more than 0."]);
            }
            if (! $product->unit?->allow_decimal && floor($line['quantity']) != $line['quantity']) {
                throw ValidationException::withMessages(['items' => "{$product->name}: quantity must be a whole number."]);
            }
            $have = (float) ($available[$product->id] ?? 0);
            if ($line['quantity'] > $have && ! $this->stock->allowsNegative()) {
                throw ValidationException::withMessages(['items' => "{$product->name}: only ".$product->formatQty($have)." available at {$from->name}."]);
            }

            return $line + ['unit_cost' => (float) $product->purchase_price];
        })->values()->all();
    }

    protected function ensureCan(User $user, int $branchId, string $action): void
    {
        $permission = $action === 'receive' ? 'transfers.receive' : 'transfers.create';

        if (! $user->can($permission) || (! $user->isOwner() && $user->branch_id !== $branchId)) {
            throw ValidationException::withMessages(['action' => $action === 'receive'
                ? 'Only the receiving branch can receive this transfer.'
                : 'Only the sending branch can send this transfer.']);
        }
    }
}
