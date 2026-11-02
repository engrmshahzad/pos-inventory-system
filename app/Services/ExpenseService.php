<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ExpenseService
{
    public function __construct(protected InvoiceNumberService $numbers)
    {
    }

    public function create(User $user, Branch $branch, array $data, ?UploadedFile $attachment = null): Expense
    {
        $date = Carbon::parse($data['expense_date']);

        $expense = Expense::create([
            'expense_no' => $this->numbers->next('EXP', $branch),
            'branch_id' => $branch->id,
            'expense_category_id' => $data['expense_category_id'],
            'amount' => round((float) $data['amount'], 2),
            'expense_date' => $date,
            'method' => $data['method'],
            'paid_to' => $data['paid_to'] ?? null,
            'note' => $data['note'] ?? null,
            // Cash paid out today comes from the till: it lowers the open shift's expected cash.
            'cash_register_id' => $data['method'] === 'cash' && $date->isToday() ? CashRegister::openFor($user, $branch->id)?->id : null,
            'user_id' => $user->id,
        ]);

        if ($attachment) {
            $expense->update(['attachment' => $attachment->store('expenses', 'public')]);
        }

        return $expense;
    }

    public function update(Expense $expense, array $data, ?UploadedFile $attachment = null, bool $removeAttachment = false): Expense
    {
        $expense->update([
            'expense_category_id' => $data['expense_category_id'],
            'amount' => round((float) $data['amount'], 2),
            'expense_date' => $data['expense_date'],
            'method' => $data['method'],
            'paid_to' => $data['paid_to'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        if ($attachment || $removeAttachment) {
            if ($expense->attachment) {
                Storage::disk('public')->delete($expense->attachment);
            }
            $expense->update(['attachment' => $attachment?->store('expenses', 'public')]);
        }

        return $expense;
    }
}
