<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\PurchaseReturn;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseReturnService
{
    public function __construct(
        protected StockService $stock,
        protected InvoiceNumberService $numbers,
    ) {
    }

    public function returnableLines(Purchase $purchase): Collection
    {
        $purchase->loadMissing(['items.returnItems', 'items.product.unit']);
        $base = (float) $purchase->subtotal + (float) $purchase->tax_amount;
        $factor = $base > 0 ? ((float) $purchase->grand_total - (float) $purchase->shipping) / $base : 1.0;
        $stock = \App\Models\BranchStock::where('branch_id', $purchase->branch_id)
            ->whereIn('product_id', $purchase->items->pluck('product_id'))->pluck('quantity', 'product_id');

        return $purchase->items->map(function (PurchaseItem $item) use ($factor, $stock) {
            $returned = (float) $item->returnItems->sum('quantity');

            return [
                'item' => $item,
                'bought' => (float) $item->quantity,
                'returned' => $returned,
                'remaining' => max(0, round((float) $item->quantity - $returned, 3)),
                'in_stock' => (float) ($stock[$item->product_id] ?? 0),
                'unit_cost' => round((float) $item->line_total / (float) $item->quantity * $factor, 2),
            ];
        });
    }

    /**
     * @param  array<int, float|string>  $quantities  [purchase_item_id => qty]
     * @param  string  $settle  'adjust' (reduce payable) or a payment method for a refund received from the supplier
     */
    public function create(User $user, Purchase $purchase, array $quantities, string $settle, ?string $reason = null): PurchaseReturn
    {
        if ($purchase->status !== Purchase::STATUS_RECEIVED) {
            throw ValidationException::withMessages(['purchase' => 'Only received purchases can be returned.']);
        }
        if ($settle !== 'adjust' && ! array_key_exists($settle, Payment::METHODS)) {
            throw ValidationException::withMessages(['settle_method' => 'Choose how the supplier settles this return.']);
        }

        return DB::transaction(function () use ($user, $purchase, $quantities, $settle, $reason) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->first();
            $lines = $this->returnableLines($purchase)->keyBy(fn ($l) => $l['item']->id);
            $rows = [];

            foreach ($quantities as $itemId => $qty) {
                $qty = round((float) $qty, 3);
                if ($qty <= 0) {
                    continue;
                }
                $line = $lines->get((int) $itemId)
                    ?? throw ValidationException::withMessages(['items' => 'An item does not belong to this purchase.']);
                if ($qty > $line['remaining']) {
                    throw ValidationException::withMessages(['items' => "{$line['item']->product_name}: you can return at most ".rtrim(rtrim(number_format($line['remaining'], 3), '0'), '.').'.']);
                }

                $rows[] = [
                    'purchase_item_id' => $line['item']->id,
                    'product_id' => $line['item']->product_id,
                    'product_name' => $line['item']->product_name,
                    'quantity' => $qty,
                    'unit_cost' => $line['unit_cost'],
                    'total' => round($qty * $line['unit_cost'], 2),
                ];
            }

            if (! $rows) {
                throw ValidationException::withMessages(['items' => 'Enter a quantity for at least one item.']);
            }

            $total = round(array_sum(array_column($rows, 'total')), 2);

            $return = PurchaseReturn::create([
                'branch_id' => $purchase->branch_id,
                'purchase_id' => $purchase->id,
                'supplier_id' => $purchase->supplier_id,
                'user_id' => $user->id,
                'return_no' => $this->numbers->next('PR', $purchase->branch),
                'total' => $total,
                'settle_method' => $settle,
                'reason' => $reason,
                'returned_at' => now(),
            ]);

            foreach ($rows as $row) {
                $return->items()->create($row);
                // Throws InsufficientStockException (and rolls back) if the goods are no longer on the shelf.
                $this->stock->decrease($purchase->branch_id, $row['product_id'], $row['quantity'], 'purchase_return', $return, $return->return_no, $reason, $row['unit_cost']);
            }

            if ($settle === 'adjust') {
                $onInvoice = min($total, (float) $purchase->due_amount);
                if ($onInvoice > 0) {
                    $due = round((float) $purchase->due_amount - $onInvoice, 2);
                    $purchase->update(['due_amount' => $due, 'payment_status' => $due <= 0 ? 'paid' : ((float) $purchase->paid_amount > 0 ? 'partial' : 'due')]);
                }
                Supplier::whereKey($purchase->supplier_id)->decrement('balance', $total);
            } else {
                $return->payments()->create([
                    'payment_no' => $this->numbers->next('RFD', $purchase->branch),
                    'branch_id' => $purchase->branch_id,
                    'type' => 'supplier_refund',
                    'supplier_id' => $purchase->supplier_id,
                    'method' => $settle,
                    'amount' => $total,
                    'cash_register_id' => $settle === 'cash' ? CashRegister::openFor($user, $purchase->branch_id)?->id : null,
                    'user_id' => $user->id,
                    'paid_at' => now(),
                ]);
            }

            activity('created', "Purchase return {$return->return_no} for {$purchase->purchase_no}: ".money($total, true), $return);

            return $return->load('items');
        });
    }
}
