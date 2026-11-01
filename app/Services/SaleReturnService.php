<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleReturnService
{
    public function __construct(
        protected StockService $stock,
        protected InvoiceNumberService $numbers,
    ) {
    }

    /**
     * Lines of a sale with sold / already returned / still returnable qty and the refund per unit.
     * Refund per unit = line total ÷ qty, reduced by the line's share of the bill discount. Other charges are not refunded.
     */
    public function returnableLines(Sale $sale): Collection
    {
        $sale->loadMissing(['items.returnItems', 'items.product.unit']);
        $factor = $this->discountFactor($sale);

        return $sale->items->map(function (SaleItem $item) use ($factor) {
            $returned = (float) $item->returnItems->sum('quantity');

            return [
                'item' => $item,
                'sold' => (float) $item->quantity,
                'returned' => $returned,
                'remaining' => max(0, round((float) $item->quantity - $returned, 3)),
                'unit_refund' => round((float) $item->line_total / (float) $item->quantity * $factor, 2),
            ];
        });
    }

    /**
     * @param  array<int, float|string>  $quantities  [sale_item_id => qty to return]
     */
    public function create(User $user, Sale $sale, array $quantities, string $refundMethod, ?string $reason = null): SaleReturn
    {
        if ($sale->status !== Sale::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['sale' => 'Only completed sales can be returned.']);
        }
        if ($refundMethod === 'adjust' && ! $sale->customer_id) {
            throw ValidationException::withMessages(['refund_method' => 'Walk-in sales cannot be adjusted in a khata. Choose a refund method.']);
        }
        if ($refundMethod !== 'adjust' && ! array_key_exists($refundMethod, Payment::METHODS)) {
            throw ValidationException::withMessages(['refund_method' => 'Choose a valid refund method.']);
        }

        return DB::transaction(function () use ($user, $sale, $quantities, $refundMethod, $reason) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->first();
            $lines = $this->returnableLines($sale)->keyBy(fn ($l) => $l['item']->id);
            $rows = [];

            foreach ($quantities as $itemId => $qty) {
                $qty = round((float) $qty, 3);
                if ($qty <= 0) {
                    continue;
                }
                $line = $lines->get((int) $itemId)
                    ?? throw ValidationException::withMessages(['items' => 'An item does not belong to this sale.']);
                $item = $line['item'];

                if ($qty > $line['remaining']) {
                    throw ValidationException::withMessages(['items' => "{$item->product_name}: you can return at most ".rtrim(rtrim(number_format($line['remaining'], 3), '0'), '.').'.']);
                }
                if (! $item->product?->unit?->allow_decimal && floor($qty) != $qty) {
                    throw ValidationException::withMessages(['items' => "{$item->product_name}: quantity must be a whole number."]);
                }

                $rows[] = [
                    'sale_item_id' => $item->id,
                    'product_id' => $item->product_id,
                    'product_name' => $item->product_name,
                    'quantity' => $qty,
                    'unit_price' => $line['unit_refund'],
                    'total' => round($qty * $line['unit_refund'], 2),
                    'unit_cost' => (float) $item->purchase_price,
                ];
            }

            if (! $rows) {
                throw ValidationException::withMessages(['items' => 'Enter a quantity for at least one item.']);
            }

            $total = round(array_sum(array_column($rows, 'total')), 2);
            $branch = $sale->branch;

            $return = SaleReturn::create([
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'user_id' => $user->id,
                'return_no' => $this->numbers->next('SR', $branch),
                'total' => $total,
                'refund_method' => $refundMethod,
                'reason' => $reason,
                'returned_at' => now(),
            ]);

            foreach ($rows as $row) {
                $unitCost = $row['unit_cost'];
                unset($row['unit_cost']);
                $return->items()->create($row);
                $this->stock->increase($sale->branch_id, $row['product_id'], $row['quantity'], 'sale_return', $return, $return->return_no, $reason, $unitCost);
            }

            if ($refundMethod === 'adjust') {
                // Reduce what the customer owes; first on this invoice, any extra becomes khata credit.
                $onInvoice = min($total, (float) $sale->due_amount);
                if ($onInvoice > 0) {
                    $due = round((float) $sale->due_amount - $onInvoice, 2);
                    $sale->update(['due_amount' => $due, 'payment_status' => $due <= 0 ? 'paid' : ((float) $sale->paid_amount > 0 ? 'partial' : 'due')]);
                }
                Customer::whereKey($sale->customer_id)->decrement('balance', $total);
            } else {
                $return->payments()->create([
                    'payment_no' => $this->numbers->next('RFD', $branch),
                    'branch_id' => $sale->branch_id,
                    'type' => 'refund',
                    'customer_id' => $sale->customer_id,
                    'method' => $refundMethod,
                    'amount' => $total,
                    'cash_register_id' => $refundMethod === 'cash' ? CashRegister::openFor($user, $sale->branch_id)?->id : null,
                    'user_id' => $user->id,
                    'paid_at' => now(),
                ]);
            }

            activity('created', "Sale return {$return->return_no} for {$sale->invoice_no}: ".money($total, true), $return);

            return $return->load('items');
        });
    }

    protected function discountFactor(Sale $sale): float
    {
        $base = (float) $sale->subtotal + (float) $sale->tax_amount;

        return $base > 0 ? ((float) $sale->grand_total - (float) $sale->shipping) / $base : 1.0;
    }
}
