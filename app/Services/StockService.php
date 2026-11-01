<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\Branch;
use App\Models\BranchStock;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only place that changes branch_stock. Every change is row-locked and written to stock_movements.
 * Callers (sales, purchases, transfers...) may wrap several calls in their own DB::transaction.
 */
class StockService
{
    public const TYPES_IN = ['opening', 'purchase', 'sale_return', 'transfer_in', 'adjustment'];
    public const TYPES_OUT = ['sale', 'purchase_return', 'transfer_out', 'adjustment'];

    public function increase(
        int $branchId,
        int $productId,
        float $quantity,
        string $type,
        ?Model $reference = null,
        ?string $referenceNo = null,
        ?string $note = null,
        ?float $unitCost = null,
    ): StockMovement {
        $this->guard($quantity, $type, self::TYPES_IN);

        return $this->move($branchId, $productId, $quantity, $type, $reference, $referenceNo, $note, $unitCost);
    }

    /**
     * @throws InsufficientStockException when stock would go below zero and negative stock is not allowed.
     */
    public function decrease(
        int $branchId,
        int $productId,
        float $quantity,
        string $type,
        ?Model $reference = null,
        ?string $referenceNo = null,
        ?string $note = null,
        ?float $unitCost = null,
    ): StockMovement {
        $this->guard($quantity, $type, self::TYPES_OUT);

        return $this->move($branchId, $productId, -$quantity, $type, $reference, $referenceNo, $note, $unitCost);
    }

    public function quantity(int $branchId, int $productId): float
    {
        return (float) BranchStock::where('branch_id', $branchId)->where('product_id', $productId)->value('quantity');
    }

    public function allowsNegative(): bool
    {
        return (bool) (int) settings('allow_negative_stock', '0');
    }

    /** Create zero-quantity rows for a new product in every branch. */
    public function ensureRowsForProduct(Product $product): void
    {
        $rows = Branch::withTrashed()->pluck('id')->map(fn ($branchId) => $this->blankRow($branchId, $product->id));
        BranchStock::insertOrIgnore($rows->all());
    }

    /** Create zero-quantity rows for a new branch for every product. */
    public function ensureRowsForBranch(Branch $branch): void
    {
        Product::withTrashed()->select('id')->chunkById(500, function ($products) use ($branch) {
            BranchStock::insertOrIgnore($products->map(fn ($p) => $this->blankRow($branch->id, $p->id))->all());
        });
    }

    protected function move(int $branchId, int $productId, float $change, string $type, ?Model $reference, ?string $referenceNo, ?string $note, ?float $unitCost): StockMovement
    {
        return DB::transaction(function () use ($branchId, $productId, $change, $type, $reference, $referenceNo, $note, $unitCost) {
            $stock = $this->lockedRow($branchId, $productId);
            $current = (float) $stock->quantity;
            $new = round($current + $change, 3);

            if ($change < 0 && $new < 0 && ! $this->allowsNegative()) {
                $product = Product::withTrashed()->find($productId);
                $branch = Branch::withTrashed()->find($branchId);
                throw new InsufficientStockException($product?->name ?? "#{$productId}", $branch?->name ?? "#{$branchId}", $current, abs($change));
            }

            $stock->quantity = $new;
            $stock->save();

            return StockMovement::create([
                'branch_id' => $branchId,
                'product_id' => $productId,
                'type' => $type,
                'quantity' => $change,
                'balance_after' => $new,
                'unit_cost' => $unitCost ?? (float) Product::withTrashed()->whereKey($productId)->value('purchase_price'),
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'reference_no' => $referenceNo,
                'user_id' => Auth::id(),
                'note' => $note,
            ]);
        });
    }

    protected function lockedRow(int $branchId, int $productId): BranchStock
    {
        $query = fn () => BranchStock::where('branch_id', $branchId)->where('product_id', $productId)->lockForUpdate()->first();

        if ($row = $query()) {
            return $row;
        }

        // Row missing (e.g. data imported outside the app): create it, then lock it.
        BranchStock::insertOrIgnore([$this->blankRow($branchId, $productId)]);

        return $query();
    }

    protected function guard(float $quantity, string $type, array $allowed): void
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Stock quantity must be greater than zero.');
        }

        if (! in_array($type, $allowed, true)) {
            throw new InvalidArgumentException("Movement type \"{$type}\" is not valid here.");
        }
    }

    protected function blankRow(int $branchId, int $productId): array
    {
        return ['branch_id' => $branchId, 'product_id' => $productId, 'quantity' => 0, 'created_at' => now(), 'updated_at' => now()];
    }
}
