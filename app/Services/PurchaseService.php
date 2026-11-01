<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseService
{
    public function __construct(
        protected StockService $stock,
        protected InvoiceNumberService $numbers,
    ) {
    }

    /**
     * Status "received" adds stock and supplier payable now; "ordered" waits for receive().
     */
    public function create(User $user, Branch $branch, array $data): Purchase
    {
        $supplier = Supplier::active()->find($data['supplier_id'] ?? null)
            ?? throw ValidationException::withMessages(['supplier_id' => 'Choose an active supplier.']);
        $totals = $this->calculate($data);
        $paid = round(max(0, (float) ($data['paid_amount'] ?? 0)), 2);

        if ($paid > $totals['grand_total']) {
            throw ValidationException::withMessages(['paid_amount' => 'Paid amount cannot be more than the purchase total.']);
        }

        return DB::transaction(function () use ($user, $branch, $data, $supplier, $totals, $paid) {
            $purchase = Purchase::create([
                'branch_id' => $branch->id,
                'supplier_id' => $supplier->id,
                'user_id' => $user->id,
                'purchase_no' => $this->numbers->next('P', $branch),
                'reference_no' => $data['reference_no'] ?? null,
                'purchase_date' => $data['purchase_date'] ?? today(),
                'status' => Purchase::STATUS_ORDERED,
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping' => $totals['shipping'],
                'grand_total' => $totals['grand_total'],
                'paid_amount' => $paid,
                'due_amount' => round($totals['grand_total'] - $paid, 2),
                'payment_status' => $this->paymentStatus($paid, $totals['grand_total']),
                'note' => $data['note'] ?? null,
            ]);

            $purchase->items()->createMany($totals['lines']);

            if ($paid > 0) {
                $method = $data['payment_method'] ?? 'cash';
                $purchase->payments()->create([
                    'payment_no' => $this->numbers->next('PAY', $branch),
                    'branch_id' => $branch->id,
                    'type' => 'purchase_payment',
                    'supplier_id' => $supplier->id,
                    'method' => $method,
                    'amount' => $paid,
                    'reference' => $data['payment_reference'] ?? null,
                    'cash_register_id' => $method === 'cash' ? CashRegister::openFor($user, $branch->id)?->id : null,
                    'user_id' => $user->id,
                    'paid_at' => now(),
                ]);
                $supplier->decrement('balance', $paid);
            }

            if (($data['status'] ?? Purchase::STATUS_RECEIVED) === Purchase::STATUS_RECEIVED) {
                $this->receive($purchase);
            }

            activity('created', "Purchase {$purchase->purchase_no} ".money($purchase->grand_total)." from {$supplier->name}", $purchase);

            return $purchase->fresh(['items', 'supplier', 'branch']);
        });
    }

    /** Adds the items to stock, updates product cost (latest purchase price) and supplier payable. */
    public function receive(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->with('items')->first();

            if ($purchase->status === Purchase::STATUS_RECEIVED) {
                throw ValidationException::withMessages(['status' => 'This purchase has already been received.']);
            }

            foreach ($purchase->items as $item) {
                $unitCost = round(((float) $item->line_total - (float) $item->tax_amount) / (float) $item->quantity, 2);
                $this->stock->increase($purchase->branch_id, $item->product_id, (float) $item->quantity, 'purchase', $purchase, $purchase->purchase_no, null, $unitCost);

                $updates = ['purchase_price' => $unitCost];
                if ($item->new_sale_price !== null) {
                    $updates['sale_price'] = $item->new_sale_price;
                }
                Product::withTrashed()->whereKey($item->product_id)->update($updates);
            }

            $purchase->update(['status' => Purchase::STATUS_RECEIVED, 'received_at' => now()]);
            Supplier::whereKey($purchase->supplier_id)->increment('balance', (float) $purchase->grand_total);

            return $purchase;
        });
    }

    /** Only orders that have not been received or paid can be deleted. */
    public function delete(Purchase $purchase): void
    {
        if ($purchase->status === Purchase::STATUS_RECEIVED) {
            throw ValidationException::withMessages(['action' => 'Received purchases cannot be deleted. Use a purchase return instead.']);
        }
        if ((float) $purchase->paid_amount > 0) {
            throw ValidationException::withMessages(['action' => 'This order has payments. It cannot be deleted.']);
        }

        $purchase->delete();
    }

    public function calculate(array $data): array
    {
        $items = collect($data['items'] ?? [])->filter(fn ($i) => ! empty($i['product_id']));
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Add at least one product.']);
        }

        $products = Product::with('unit')->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');

        $lines = $items->values()->map(function ($item) use ($products) {
            $product = $products->get($item['product_id'])
                ?? throw ValidationException::withMessages(['items' => 'A selected product no longer exists.']);
            $qty = round((float) ($item['quantity'] ?? 0), 3);

            if ($qty <= 0) {
                throw ValidationException::withMessages(['items' => "{$product->name}: quantity must be more than 0."]);
            }
            if (! $product->unit?->allow_decimal && floor($qty) != $qty) {
                throw ValidationException::withMessages(['items' => "{$product->name}: quantity must be a whole number."]);
            }

            $cost = max(0, round((float) ($item['unit_cost'] ?? $product->purchase_price), 2));
            $gross = round($qty * $cost, 2);
            $discount = min(max(0, round((float) ($item['discount'] ?? 0), 2)), $gross);
            $net = round($gross - $discount, 2);
            $taxPercent = min(100, max(0, (float) ($item['tax_percent'] ?? 0)));
            $tax = round($net * $taxPercent / 100, 2);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'discount' => $discount,
                'tax_percent' => $taxPercent,
                'tax_amount' => $tax,
                'line_total' => round($net + $tax, 2),
                'new_sale_price' => ! empty($item['update_price']) && isset($item['new_sale_price']) && $item['new_sale_price'] !== '' ? round((float) $item['new_sale_price'], 2) : null,
                'expiry_date' => $product->track_expiry ? ($item['expiry_date'] ?? null) ?: null : null,
                'batch_no' => $product->track_expiry ? ($item['batch_no'] ?? null) ?: null : null,
            ];
        });

        $subtotal = round($lines->sum(fn ($l) => $l['line_total'] - $l['tax_amount']), 2);
        $tax = round($lines->sum('tax_amount'), 2);
        $discount = min(max(0, round((float) ($data['discount'] ?? 0), 2)), $subtotal);
        $shipping = max(0, round((float) ($data['shipping'] ?? 0), 2));

        return [
            'lines' => $lines->all(),
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax_amount' => $tax,
            'shipping' => $shipping,
            'grand_total' => round($subtotal - $discount + $tax + $shipping, 2),
        ];
    }

    public function paymentStatus(float $paid, float $total): string
    {
        return match (true) {
            $paid >= $total => 'paid',
            $paid > 0 => 'partial',
            default => 'due',
        };
    }
}
