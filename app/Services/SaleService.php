<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates sales from POS carts. All prices and totals are recalculated here; the browser's numbers are never trusted.
 */
class SaleService
{
    public function __construct(
        protected StockService $stock,
        protected InvoiceNumberService $numbers,
    ) {
    }

    /**
     * @param  array{customer_id?: ?int, items: array, discount_type?: string, discount_value?: float, shipping?: float,
     *               payments?: array, note?: ?string, hold_id?: ?int}  $data
     */
    public function complete(User $user, Branch $branch, CashRegister $register, array $data): Sale
    {
        $customer = $this->customer($data['customer_id'] ?? null);
        $totals = $this->calculate($user, $data);
        [$payments, $paid, $change] = $this->payments($data['payments'] ?? [], $totals['grand_total']);
        $due = round($totals['grand_total'] - $paid, 2);

        if ($due > 0 && ! $customer) {
            throw ValidationException::withMessages(['payments' => 'Credit or partial payment needs a customer. Select a customer or collect the full amount.']);
        }

        return DB::transaction(function () use ($user, $branch, $register, $data, $customer, $totals, $payments, $paid, $change, $due) {
            $sale = Sale::create([
                'branch_id' => $branch->id,
                'invoice_no' => $this->numbers->next('S', $branch),
                'status' => Sale::STATUS_COMPLETED,
                'customer_id' => $customer?->id,
                'user_id' => $user->id,
                'cash_register_id' => $register->id,
                'subtotal' => $totals['subtotal'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping' => $totals['shipping'],
                'grand_total' => $totals['grand_total'],
                'paid_amount' => $paid,
                'due_amount' => $due,
                'change_amount' => $change,
                'payment_status' => $this->paymentStatus($paid, $totals['grand_total']),
                'note' => $data['note'] ?? null,
                'sold_at' => now(),
            ]);

            foreach ($totals['lines'] as $line) {
                $sale->items()->create($line);
                $this->stock->decrease($branch->id, $line['product_id'], $line['quantity'], 'sale', $sale, $sale->invoice_no, null, $line['purchase_price']);
            }

            foreach ($payments as $payment) {
                $sale->payments()->create($payment + [
                    'branch_id' => $branch->id,
                    'type' => 'sale',
                    'customer_id' => $customer?->id,
                    'cash_register_id' => $register->id,
                    'user_id' => $user->id,
                    'paid_at' => now(),
                ]);
            }

            if ($due > 0) {
                $customer->increment('balance', $due);
            }

            // A resumed held bill is replaced by the completed sale.
            if (! empty($data['hold_id'])) {
                $this->heldQuery($user, $branch)->whereKey($data['hold_id'])->first()?->delete();
            }

            return $sale->load(['items', 'payments', 'customer', 'branch', 'user']);
        });
    }

    public function hold(User $user, Branch $branch, array $data): Sale
    {
        $customer = $this->customer($data['customer_id'] ?? null);
        $totals = $this->calculate($user, $data);

        return DB::transaction(function () use ($user, $branch, $data, $customer, $totals) {
            if (! empty($data['hold_id'])) {
                $this->heldQuery($user, $branch)->whereKey($data['hold_id'])->first()?->delete();
            }

            $sale = Sale::create([
                'branch_id' => $branch->id,
                'status' => Sale::STATUS_HOLD,
                'customer_id' => $customer?->id,
                'user_id' => $user->id,
                'subtotal' => $totals['subtotal'],
                'discount_type' => $totals['discount_type'],
                'discount_value' => $totals['discount_value'],
                'discount_amount' => $totals['discount_amount'],
                'tax_amount' => $totals['tax_amount'],
                'shipping' => $totals['shipping'],
                'grand_total' => $totals['grand_total'],
                'due_amount' => $totals['grand_total'],
                'payment_status' => 'due',
                'note' => $data['note'] ?? null,
            ]);

            $sale->items()->createMany($totals['lines']);

            return $sale;
        });
    }

    /** Held bills visible to this user at this branch (cashiers: only their own). */
    public function heldQuery(User $user, Branch $branch)
    {
        return Sale::held()->where('branch_id', $branch->id)
            ->when($user->isCashier(), fn ($q) => $q->where('user_id', $user->id));
    }

    public function receivePayment(Sale $sale, User $user, float $amount, string $method, ?string $reference = null, ?string $note = null): Payment
    {
        if ($sale->status !== Sale::STATUS_COMPLETED) {
            throw ValidationException::withMessages(['amount' => 'Payments can only be received on completed sales.']);
        }
        if (! array_key_exists($method, Payment::METHODS)) {
            throw ValidationException::withMessages(['method' => 'Choose a valid payment method.']);
        }

        return DB::transaction(function () use ($sale, $user, $amount, $method, $reference, $note) {
            $sale = Sale::whereKey($sale->id)->lockForUpdate()->first();
            $amount = round($amount, 2);

            if ($amount <= 0 || $amount > (float) $sale->due_amount) {
                throw ValidationException::withMessages(['amount' => 'Amount must be between 1 and the due amount ('.money($sale->due_amount, true).').']);
            }

            $payment = $sale->payments()->create([
                'payment_no' => $this->numbers->next('RCV', $sale->branch),
                'branch_id' => $sale->branch_id,
                'type' => 'customer_receipt',
                'customer_id' => $sale->customer_id,
                'method' => $method,
                'amount' => $amount,
                'reference' => $reference,
                'note' => $note,
                'cash_register_id' => $method === 'cash' ? CashRegister::openFor($user, $sale->branch_id)?->id : null,
                'user_id' => $user->id,
                'paid_at' => now(),
            ]);

            $paid = round((float) $sale->paid_amount + $amount, 2);
            $sale->update([
                'paid_amount' => $paid,
                'due_amount' => round((float) $sale->grand_total - $paid, 2),
                'payment_status' => $this->paymentStatus($paid, (float) $sale->grand_total),
            ]);

            if ($sale->customer_id) {
                Customer::whereKey($sale->customer_id)->decrement('balance', $amount);
            }

            activity('payment_received', "Received ".money($amount, true)." on {$sale->invoice_no}", $sale);

            return $payment;
        });
    }

    /**
     * Server-side cart maths. Line: qty × price − line discount (+ tax on that). Bill discount comes off the subtotal.
     *
     * @return array{lines: list<array>, subtotal: float, discount_type: string, discount_value: float, discount_amount: float,
     *               tax_amount: float, shipping: float, grand_total: float}
     */
    public function calculate(User $user, array $data): array
    {
        $items = collect($data['items'] ?? [])->filter(fn ($i) => ! empty($i['product_id']));

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'The cart is empty.']);
        }

        $canEditPrice = $user->can('sales.edit_price');
        $canDiscount = $user->can('sales.discount');

        $products = Product::with('unit')->whereIn('id', $items->pluck('product_id'))->get()->keyBy('id');

        $lines = $items->values()->map(function ($item, $index) use ($products, $canEditPrice, $canDiscount) {
            $product = $products->get($item['product_id']);
            $row = 'Line '.($index + 1);

            if (! $product || ! $product->is_active) {
                throw ValidationException::withMessages(['items' => "{$row}: this product is not available for sale."]);
            }

            $qty = round((float) ($item['quantity'] ?? 0), 3);
            if ($qty <= 0) {
                throw ValidationException::withMessages(['items' => "{$product->name}: quantity must be more than 0."]);
            }
            if (! $product->unit?->allow_decimal && floor($qty) != $qty) {
                throw ValidationException::withMessages(['items' => "{$product->name}: quantity must be a whole number."]);
            }

            $price = $canEditPrice && isset($item['unit_price']) && is_numeric($item['unit_price'])
                ? max(0, round((float) $item['unit_price'], 2))
                : (float) $product->sale_price;

            $gross = round($qty * $price, 2);
            $discount = $canDiscount ? min(max(0, round((float) ($item['discount'] ?? 0), 2)), $gross) : 0.0;
            $net = round($gross - $discount, 2);
            $taxPercent = (float) $product->tax_percent;
            $tax = round($net * $taxPercent / 100, 2);

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku,
                'quantity' => $qty,
                'unit_price' => $price,
                'purchase_price' => (float) $product->purchase_price,
                'discount' => $discount,
                'tax_percent' => $taxPercent,
                'tax_amount' => $tax,
                'line_total' => round($net + $tax, 2),
            ];
        });

        $subtotal = round($lines->sum(fn ($l) => $l['line_total'] - $l['tax_amount']), 2);
        $tax = round($lines->sum('tax_amount'), 2);

        $discountType = ($data['discount_type'] ?? 'amount') === 'percent' ? 'percent' : 'amount';
        $discountValue = $canDiscount ? max(0, round((float) ($data['discount_value'] ?? 0), 2)) : 0.0;
        $discountAmount = $discountType === 'percent'
            ? round($subtotal * min($discountValue, 100) / 100, 2)
            : min($discountValue, $subtotal);
        $shipping = max(0, round((float) ($data['shipping'] ?? 0), 2));

        return [
            'lines' => $lines->all(),
            'subtotal' => $subtotal,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'discount_amount' => $discountAmount,
            'tax_amount' => $tax,
            'shipping' => $shipping,
            'grand_total' => round($subtotal - $discountAmount + $tax + $shipping, 2),
        ];
    }

    /**
     * Cleans the tendered payments. Only cash can be over-tendered; the extra becomes change.
     *
     * @return array{0: list<array>, 1: float, 2: float} [payment rows, amount applied to the bill, change]
     */
    protected function payments(array $input, float $grandTotal): array
    {
        $rows = collect($input)
            ->map(fn ($p) => ['method' => (string) ($p['method'] ?? ''), 'amount' => round((float) ($p['amount'] ?? 0), 2), 'reference' => $p['reference'] ?? null])
            ->filter(fn ($p) => $p['amount'] > 0);

        if ($rows->contains(fn ($p) => ! array_key_exists($p['method'], Payment::METHODS))) {
            throw ValidationException::withMessages(['payments' => 'Invalid payment method.']);
        }

        $tendered = round($rows->sum('amount'), 2);
        $nonCash = round($rows->where('method', '!=', 'cash')->sum('amount'), 2);

        if ($nonCash > $grandTotal) {
            throw ValidationException::withMessages(['payments' => 'Card / wallet / bank amounts cannot be more than the bill. Only cash can give change.']);
        }

        $change = max(0, round($tendered - $grandTotal, 2));

        // Record cash net of change, so payment rows add up to what the shop actually kept.
        $rows = $rows->groupBy('method')->map(fn (Collection $group, $method) => [
            'method' => $method,
            'amount' => round($group->sum('amount') - ($method === 'cash' ? $change : 0), 2),
            'reference' => $group->pluck('reference')->filter()->implode(', ') ?: null,
        ])->filter(fn ($p) => $p['amount'] > 0)->values()->all();

        return [$rows, round(min($tendered, $grandTotal), 2), $change];
    }

    protected function customer(mixed $id): ?Customer
    {
        if (! $id) {
            return null;
        }

        return Customer::active()->find($id)
            ?? throw ValidationException::withMessages(['customer_id' => 'Selected customer was not found or is inactive.']);
    }

    protected function paymentStatus(float $paid, float $total): string
    {
        return match (true) {
            $paid >= $total => 'paid',
            $paid > 0 => 'partial',
            default => 'due',
        };
    }
}
