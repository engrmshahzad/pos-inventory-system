<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    /**
     * Branch documents: owner sees all; others only their own branch (and cashiers only what they created).
     */
    protected function ensureBranchAccess(int $branchId, ?int $createdBy = null): void
    {
        $user = auth()->user();

        if ($user->isOwner()) {
            return;
        }

        abort_unless($branchId === $user->branch_id, 403);
        abort_if($user->isCashier() && $createdBy !== null && $createdBy !== $user->id, 403);
    }

    /** Branch for new documents: owner must have one selected in the switcher. */
    protected function workingBranch(): \App\Models\Branch
    {
        return currentBranch() ?? abort(redirect()->back()->withErrors(['action' => 'Choose a branch in the branch switcher first.']));
    }
}
