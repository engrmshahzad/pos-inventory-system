<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class CashRegisterService
{
    public function open(User $user, int $branchId, float $openingCash, ?string $note = null): CashRegister
    {
        if (CashRegister::openFor($user, $branchId)) {
            throw ValidationException::withMessages(['opening_cash' => 'You already have an open shift at this branch.']);
        }

        $register = CashRegister::create([
            'branch_id' => $branchId,
            'user_id' => $user->id,
            'status' => 'open',
            'opening_cash' => $openingCash,
            'opening_note' => $note,
            'opened_at' => now(),
        ]);

        activity('shift_opened', "Shift opened with ".money($openingCash), $register);

        return $register;
    }

    public function close(CashRegister $register, float $countedCash, ?string $note = null): CashRegister
    {
        if ($register->status !== 'open') {
            throw ValidationException::withMessages(['counted_cash' => 'This shift is already closed.']);
        }

        $expected = $this->summary($register)['expected_cash'];

        $register->update([
            'status' => 'closed',
            'expected_cash' => $expected,
            'counted_cash' => $countedCash,
            'difference' => round($countedCash - $expected, 2),
            'closing_note' => $note,
            'closed_at' => now(),
        ]);

        activity('shift_closed', 'Shift closed. Difference '.money($register->difference), $register);

        return $register;
    }

    /**
     * Expected cash = opening + cash received (sales, due received, supplier refunds)
     *               − cash paid out (sale refunds, supplier payments) during this shift.
     */
    public function summary(CashRegister $register): array
    {
        $rows = Payment::where('cash_register_id', $register->id)
            ->selectRaw('method, type, SUM(amount) as total')
            ->groupBy('method', 'type')
            ->get();

        $in = fn (string $method) => (float) $rows->where('method', $method)->whereIn('type', Payment::TYPES_IN)->sum('total');
        $cashOut = (float) $rows->where('method', 'cash')->whereIn('type', Payment::TYPES_OUT)->sum('total');
        $refunds = (float) $rows->where('method', 'cash')->where('type', 'refund')->sum('total');

        $sales = $register->sales()->where('status', 'completed');

        return [
            'opening_cash' => (float) $register->opening_cash,
            'cash_in' => $in('cash'),
            'cash_refunds' => $refunds,
            'cash_paid_out' => $cashOut - $refunds,
            'expected_cash' => round((float) $register->opening_cash + $in('cash') - $cashOut, 2),
            'methods' => collect(Payment::METHODS)->map(fn ($label, $key) => ['label' => $label, 'total' => $in($key)]),
            'sales_count' => (clone $sales)->count(),
            'sales_total' => (float) (clone $sales)->sum('grand_total'),
            'credit_given' => (float) (clone $sales)->sum('due_amount'),
            'received_due' => (float) $rows->where('type', 'customer_receipt')->sum('total'),
        ];
    }
}
