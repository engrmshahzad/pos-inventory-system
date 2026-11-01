<?php

namespace App\Policies;

use App\Models\User;

/**
 * Owner manages every user. A manager manages only the cashiers of their own branch.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view') && ($user->isOwner() || $user->isManager());
    }

    public function create(User $user): bool
    {
        return $user->can('users.create') && ($user->isOwner() || $user->isManager());
    }

    public function update(User $user, User $target): bool
    {
        return $user->can('users.edit') && $this->manages($user, $target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can('users.delete') && $user->id !== $target->id && $this->manages($user, $target);
    }

    protected function manages(User $user, User $target): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        return $user->isManager()
            && $target->isCashier()
            && $target->branch_id !== null
            && $target->branch_id === $user->branch_id;
    }
}
