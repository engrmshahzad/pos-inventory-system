<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates the owner's selected branch and shares the current branch with every view.
 */
class SetCurrentBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $switchableBranches = collect();

        if ($user->isOwner()) {
            $switchableBranches = Branch::active()->orderBy('name')->get(['id', 'name', 'code', 'city']);
            $selected = $request->session()->get('current_branch_id');

            if ($selected && ! $switchableBranches->contains('id', (int) $selected)) {
                $request->session()->forget('current_branch_id');
            }
        }

        View::share('currentBranch', currentBranch());
        View::share('switchableBranches', $switchableBranches);

        return $next($request);
    }
}
