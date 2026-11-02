<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Product;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Notifications\AppNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock adjustments. Owner's apply at once; a manager's adjustment whose value (sum of |qty × cost|)
 * is above the approval threshold waits for the owner. Stock only changes when approved.
 */
class AdjustmentService
{
    public function __construct(
        protected StockService $stock,
        protected InvoiceNumberService $numbers,
        protected NotifyService $notify,
    ) {
    }

    public function threshold(): float
    {
        return (float) settings('adjustment_approval_threshold', 5000);
    }

    /**
     * @param  array{adjustment_date?: string, reason: string, note?: ?string, items: array, source?: string}  $data
     *         items: [{product_id, quantity (signed), note?}]
     */
    public function create(User $user, Branch $branch, array $data): StockAdjustment
    {
        if (! array_key_exists($data['reason'] ?? '', StockAdjustment::REASONS)) {
            throw ValidationException::withMessages(['reason' => 'Choose a reason.']);
        }

        $lines = $this->lines($data['items'] ?? []);
        $impact = round(array_sum(array_map(fn ($l) => abs($l['value']), $lines)), 2);
        $needsApproval = ! $user->isOwner() && $impact > $this->threshold();

        return DB::transaction(function () use ($user, $branch, $data, $lines, $needsApproval) {
            $adjustment = StockAdjustment::create([
                'adjustment_no' => $this->numbers->next('ADJ', $branch),
                'branch_id' => $branch->id,
                'adjustment_date' => $data['adjustment_date'] ?? today(),
                'reason' => $data['reason'],
                'source' => $data['source'] ?? 'manual',
                'status' => $needsApproval ? 'pending' : 'approved',
                'total_value' => round(array_sum(array_column($lines, 'value')), 2),
                'note' => $data['note'] ?? null,
                'created_by' => $user->id,
                'approved_by' => $needsApproval ? null : $user->id,
                'approved_at' => $needsApproval ? null : now(),
            ]);
            $adjustment->items()->createMany($lines);

            if ($needsApproval) {
                activity('created', "Adjustment {$adjustment->adjustment_no} awaiting approval", $adjustment);
                $this->notify->send($this->notify->owners(), new AppNotification(
                    'Adjustment needs approval',
                    "{$user->name} at {$branch->name}: {$adjustment->adjustment_no} ({$adjustment->reason_label}, ".money(abs((float) $adjustment->total_value)).').',
                    route('adjustments.show', $adjustment), 'sliders', 'warning', 'adjustment_pending',
                ));
            } else {
                $this->apply($adjustment);
                activity('created', "Adjustment {$adjustment->adjustment_no} applied", $adjustment);
            }

            return $adjustment;
        });
    }

    public function approve(StockAdjustment $adjustment, User $user): StockAdjustment
    {
        $this->ensureApprover($user);

        return DB::transaction(function () use ($adjustment, $user) {
            $adjustment = StockAdjustment::whereKey($adjustment->id)->lockForUpdate()->first();
            if ($adjustment->status !== 'pending') {
                throw ValidationException::withMessages(['action' => 'This adjustment is not waiting for approval.']);
            }

            $this->apply($adjustment);
            $adjustment->update(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()]);
            activity('approved', "Adjustment {$adjustment->adjustment_no} approved", $adjustment);
            $this->notifyCreator($adjustment, 'approved');

            return $adjustment;
        });
    }

    public function reject(StockAdjustment $adjustment, User $user, ?string $reason): StockAdjustment
    {
        $this->ensureApprover($user);

        if ($adjustment->status !== 'pending') {
            throw ValidationException::withMessages(['action' => 'This adjustment is not waiting for approval.']);
        }

        $adjustment->update(['status' => 'rejected', 'approved_by' => $user->id, 'approved_at' => now(), 'rejection_reason' => $reason]);
        activity('rejected', "Adjustment {$adjustment->adjustment_no} rejected", $adjustment);
        $this->notifyCreator($adjustment, 'rejected');

        return $adjustment;
    }

    protected function apply(StockAdjustment $adjustment): void
    {
        foreach ($adjustment->items()->get() as $item) {
            $qty = (float) $item->quantity;
            $args = [$adjustment->branch_id, $item->product_id, abs($qty), 'adjustment', $adjustment, $adjustment->adjustment_no, $adjustment->reason_label, (float) $item->unit_cost];
            $qty > 0 ? $this->stock->increase(...$args) : $this->stock->decrease(...$args);
        }
    }

    protected function lines(array $items): array
    {
        $items = collect($items)->filter(fn ($i) => ! empty($i['product_id']) && (float) ($i['quantity'] ?? 0) != 0);
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one product with a + or − quantity.']);
        }

        $products = Product::with('unit')->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');

        return $items->values()->map(function ($i) use ($products) {
            $product = $products->get($i['product_id']) ?? throw ValidationException::withMessages(['items' => 'A selected product no longer exists.']);
            $qty = round((float) $i['quantity'], 3);
            if (! $product->unit?->allow_decimal && floor(abs($qty)) != abs($qty)) {
                throw ValidationException::withMessages(['items' => "{$product->name}: quantity must be a whole number."]);
            }
            $cost = (float) $product->purchase_price;

            return [
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'value' => round($qty * $cost, 2),
                'note' => $i['note'] ?? null,
            ];
        })->all();
    }

    protected function ensureApprover(User $user): void
    {
        if (! $user->isOwner() || ! $user->can('adjustments.approve')) {
            throw ValidationException::withMessages(['action' => 'Only the owner can approve or reject adjustments.']);
        }
    }

    protected function notifyCreator(StockAdjustment $adjustment, string $outcome): void
    {
        $creator = User::find($adjustment->created_by);
        if ($creator && $creator->id !== auth()->id()) {
            $creator->notify(new AppNotification(
                "Adjustment {$outcome}",
                "{$adjustment->adjustment_no} was {$outcome}".($adjustment->rejection_reason ? ": {$adjustment->rejection_reason}" : '.'),
                route('adjustments.show', $adjustment), $outcome === 'approved' ? 'check-circle' : 'x-circle', $outcome === 'approved' ? 'success' : 'danger', 'adjustment_reviewed',
            ));
        }
    }
}
