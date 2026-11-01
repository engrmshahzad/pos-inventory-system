<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * General payments: money received from a customer or paid to a supplier, spread over open invoices
 * (oldest first, or the invoices the user picked). Whatever is left reduces the opening balance / becomes an advance.
 */
class PaymentService
{
    public function __construct(protected InvoiceNumberService $numbers)
    {
    }

    /** @param  array<int, float>|null  $allocations  [sale_id => amount], null = oldest first */
    public function receiveFromCustomer(User $user, Branch $branch, Customer $customer, array $data, ?array $allocations = null): Payment
    {
        return DB::transaction(function () use ($user, $branch, $customer, $data, $allocations) {
            $payment = $this->createPayment($user, $branch, $data, 'customer_receipt', 'RCV', ['customer_id' => $customer->id]);

            $open = Sale::completed()->where('customer_id', $customer->id)->where('due_amount', '>', 0)
                ->orderBy('sold_at')->orderBy('id')->lockForUpdate()->get();

            $this->allocate($payment, $open, $allocations, function (Sale $sale, float $amount) {
                $paid = round((float) $sale->paid_amount + $amount, 2);
                $sale->update([
                    'paid_amount' => $paid,
                    'due_amount' => round((float) $sale->grand_total - $paid, 2),
                    'payment_status' => $paid >= (float) $sale->grand_total ? 'paid' : 'partial',
                ]);
            });

            $customer->decrement('balance', (float) $payment->amount);
            activity('payment_received', "Received {$payment->payment_no} ".money($payment->amount, true)." from {$customer->name}", $payment);

            return $payment->load('allocations.allocatable');
        });
    }

    /** @param  array<int, float>|null  $allocations  [purchase_id => amount], null = oldest first */
    public function payToSupplier(User $user, Branch $branch, Supplier $supplier, array $data, ?array $allocations = null): Payment
    {
        return DB::transaction(function () use ($user, $branch, $supplier, $data, $allocations) {
            $payment = $this->createPayment($user, $branch, $data, 'supplier_payment', 'PAY', ['supplier_id' => $supplier->id]);

            $open = Purchase::received()->where('supplier_id', $supplier->id)->where('due_amount', '>', 0)
                ->orderBy('purchase_date')->orderBy('id')->lockForUpdate()->get();

            $this->allocate($payment, $open, $allocations, function (Purchase $purchase, float $amount) {
                $paid = round((float) $purchase->paid_amount + $amount, 2);
                $purchase->update([
                    'paid_amount' => $paid,
                    'due_amount' => round((float) $purchase->grand_total - $paid, 2),
                    'payment_status' => $paid >= (float) $purchase->grand_total ? 'paid' : 'partial',
                ]);
            });

            $supplier->decrement('balance', (float) $payment->amount);
            activity('payment_made', "Paid {$payment->payment_no} ".money($payment->amount, true)." to {$supplier->name}", $payment);

            return $payment->load('allocations.allocatable');
        });
    }

    protected function createPayment(User $user, Branch $branch, array $data, string $type, string $prefix, array $party): Payment
    {
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be more than 0.']);
        }
        if (! array_key_exists($data['method'] ?? '', Payment::METHODS)) {
            throw ValidationException::withMessages(['method' => 'Choose a valid payment method.']);
        }

        $paidAt = ! empty($data['paid_at']) ? \Illuminate\Support\Carbon::parse($data['paid_at'])->setTimeFrom(now()) : now();

        return Payment::create($party + [
            'payment_no' => $this->numbers->next($prefix, $branch),
            'branch_id' => $branch->id,
            'type' => $type,
            'method' => $data['method'],
            'amount' => $amount,
            'reference' => $data['reference'] ?? null,
            'note' => $data['note'] ?? null,
            // Only today's cash belongs to the open shift.
            'cash_register_id' => $data['method'] === 'cash' && $paidAt->isToday() ? CashRegister::openFor($user, $branch->id)?->id : null,
            'user_id' => $user->id,
            'paid_at' => $paidAt,
        ]);
    }

    /**
     * @param  Collection<int, Model>  $open  invoices with due > 0, oldest first
     */
    protected function allocate(Payment $payment, Collection $open, ?array $manual, callable $apply): void
    {
        $remaining = (float) $payment->amount;
        $plan = [];

        if ($manual !== null) {
            $manual = array_filter(array_map(fn ($v) => round((float) $v, 2), $manual), fn ($v) => $v > 0);
            if (round(array_sum($manual), 2) > $remaining) {
                throw ValidationException::withMessages(['allocations' => 'Allocated amounts add up to more than the payment.']);
            }
            foreach ($manual as $id => $amount) {
                $invoice = $open->firstWhere('id', (int) $id)
                    ?? throw ValidationException::withMessages(['allocations' => 'One of the selected invoices has no balance due.']);
                if ($amount > (float) $invoice->due_amount) {
                    throw ValidationException::withMessages(['allocations' => 'You cannot allocate more than the due amount of an invoice.']);
                }
                $plan[] = [$invoice, $amount];
            }
        } else {
            foreach ($open as $invoice) {
                if ($remaining <= 0) {
                    break;
                }
                $amount = min($remaining, (float) $invoice->due_amount);
                $plan[] = [$invoice, $amount];
                $remaining = round($remaining - $amount, 2);
            }
        }

        foreach ($plan as [$invoice, $amount]) {
            $payment->allocations()->create([
                'allocatable_type' => $invoice->getMorphClass(),
                'allocatable_id' => $invoice->id,
                'amount' => $amount,
            ]);
            $apply($invoice, $amount);
        }
    }
}
