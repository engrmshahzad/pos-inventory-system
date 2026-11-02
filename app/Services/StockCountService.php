<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Physical stock count: snapshot system qty → enter counted qty → apply differences as one adjustment.
 */
class StockCountService
{
    public function __construct(
        protected InvoiceNumberService $numbers,
        protected AdjustmentService $adjustments,
    ) {
    }

    public function start(User $user, Branch $branch, ?int $categoryId = null, ?string $note = null): StockCount
    {
        if (StockCount::where('branch_id', $branch->id)->where('status', 'in_progress')->exists()) {
            throw ValidationException::withMessages(['action' => "A stock count is already in progress at {$branch->name}. Finish or cancel it first."]);
        }

        return DB::transaction(function () use ($user, $branch, $categoryId, $note) {
            $count = StockCount::create([
                'count_no' => $this->numbers->next('CNT', $branch),
                'status' => 'in_progress',
                'branch_id' => $branch->id,
                'category_id' => $categoryId,
                'note' => $note,
                'created_by' => $user->id,
            ]);

            $productIds = Product::active()
                ->when($categoryId, fn ($q) => $q->where(fn ($q) => $q->where('category_id', $categoryId)
                    ->orWhereIn('category_id', Category::where('parent_id', $categoryId)->select('id'))))
                ->orderBy('name')->pluck('id');

            if ($productIds->isEmpty()) {
                throw ValidationException::withMessages(['category_id' => 'No active products to count in this selection.']);
            }

            $system = BranchStock::where('branch_id', $branch->id)->whereIn('product_id', $productIds)->pluck('quantity', 'product_id');
            $count->items()->createMany($productIds->map(fn ($id) => ['product_id' => $id, 'system_quantity' => (float) ($system[$id] ?? 0)])->all());

            activity('created', "Stock count {$count->count_no} started", $count);

            return $count;
        });
    }

    /** @param  array<int, float|string|null>  $counted  [item_id => counted qty or blank] */
    public function saveCounts(StockCount $count, array $counted): void
    {
        $this->ensureOpen($count);

        DB::transaction(function () use ($count, $counted) {
            $items = $count->items()->whereIn('id', array_keys($counted))->get()->keyBy('id');
            foreach ($counted as $id => $qty) {
                if ($item = $items->get((int) $id)) {
                    $value = $qty === null || $qty === '' ? null : max(0, round((float) $qty, 3));
                    $item->update(['counted_quantity' => $value]);
                }
            }
        });
    }

    public function apply(StockCount $count, User $user): StockCount
    {
        $this->ensureOpen($count);

        return DB::transaction(function () use ($count, $user) {
            $lines = $count->items()->whereNotNull('counted_quantity')->get()
                ->filter(fn ($i) => $i->difference != 0)
                ->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->difference, 'note' => 'System '.(float) $i->system_quantity.', counted '.(float) $i->counted_quantity])
                ->values()->all();

            $adjustment = null;
            if ($lines) {
                $adjustment = $this->adjustments->create($user, $count->branch, [
                    'reason' => 'correction',
                    'source' => 'stock_count',
                    'note' => "From stock count {$count->count_no}",
                    'items' => $lines,
                ]);
            }

            $count->update(['status' => 'applied', 'applied_at' => now(), 'stock_adjustment_id' => $adjustment?->id]);
            activity('applied', "Stock count {$count->count_no} applied", $count);

            return $count;
        });
    }

    public function cancel(StockCount $count): void
    {
        $this->ensureOpen($count);
        $count->update(['status' => 'cancelled']);
    }

    protected function ensureOpen(StockCount $count): void
    {
        if ($count->status !== 'in_progress') {
            throw ValidationException::withMessages(['action' => 'This stock count is already closed.']);
        }
    }
}
