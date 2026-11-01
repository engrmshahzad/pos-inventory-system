<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchSwitchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $request->validate(['branch' => ['required']]);

        if ($request->input('branch') === 'all') {
            $request->session()->forget('current_branch_id');
            $label = 'All Branches';
        } else {
            $branch = Branch::active()->findOrFail((int) $request->input('branch'));
            $request->session()->put('current_branch_id', $branch->id);
            $label = $branch->name;
        }

        activity('branch_switched', "Switched to {$label}");

        return back()->with('success', "Now viewing: {$label}");
    }
}
