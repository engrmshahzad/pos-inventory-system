<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner() && $user->can('branches.manage');
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Branch $branch): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, Branch $branch): bool
    {
        return $this->viewAny($user);
    }
}
