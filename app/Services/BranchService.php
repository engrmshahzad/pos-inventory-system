<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BranchService
{
    public function __construct(protected StockService $stock)
    {
    }

    public function create(array $data): Branch
    {
        return DB::transaction(function () use ($data) {
            $branch = Branch::create($data);
            $this->stock->ensureRowsForBranch($branch);

            return $branch;
        });
    }

    public function update(Branch $branch, array $data): Branch
    {
        $branch->update($data);

        return $branch;
    }

    public function toggle(Branch $branch): Branch
    {
        $branch->update(['is_active' => ! $branch->is_active]);

        return $branch;
    }

    /**
     * @throws ValidationException when the branch still has staff or any transactions.
     */
    public function delete(Branch $branch): void
    {
        if ($branch->hasTransactions()) {
            throw ValidationException::withMessages([
                'branch' => "{$branch->name} has transactions and cannot be deleted. Deactivate it instead.",
            ]);
        }

        if ($branch->users()->exists()) {
            throw ValidationException::withMessages([
                'branch' => "{$branch->name} still has staff assigned. Move or delete them first, or deactivate the branch.",
            ]);
        }

        $branch->delete();
    }
}
