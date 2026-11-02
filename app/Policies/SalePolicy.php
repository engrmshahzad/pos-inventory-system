<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /** Owner: any sale. Manager: own branch. Cashier: only their own sales. */
    public function view(User $user, Sale $sale): bool
    {
        if (! $user->can('sales.view')) {
            return false;
        }

        if ($user->isOwner()) {
            return true;
        }

        if ($sale->branch_id !== $user->branch_id) {
            return false;
        }

        return ! $user->isCashier() || $sale->user_id === $user->id;
    }

    public function receivePayment(User $user, Sale $sale): bool
    {
        return $this->view($user, $sale)
            && $sale->status === Sale::STATUS_COMPLETED
            && ($user->can('payments.create') || $user->can('sales.create'))
            && (float) $sale->due_amount > 0;
    }
}
