<?php

namespace App\Http\Controllers;

use App\Http\Requests\Branch\BranchRequest;
use App\Models\Branch;
use App\Services\BranchService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BranchController extends Controller
{
    public function __construct(protected BranchService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Branch::class);

        $branches = Branch::query()
            ->withCount('users')
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('code', 'like', $term)->orWhere('city', 'like', $term));
            })
            ->when($request->input('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->orderBy('name')
            ->get();

        return view('branches.index', [
            'branches' => $branches,
            'stats' => [
                'total' => Branch::count(),
                'active' => Branch::where('is_active', true)->count(),
                'staff' => \App\Models\User::whereNotNull('branch_id')->count(),
            ],
        ]);
    }

    public function store(BranchRequest $request): RedirectResponse
    {
        $branch = $this->service->create($request->validated());

        return redirect()->route('branches.index')->with('success', "Branch \"{$branch->name}\" created.");
    }

    public function update(BranchRequest $request, Branch $branch): RedirectResponse
    {
        $this->service->update($branch, $request->validated());

        return redirect()->route('branches.index')->with('success', "Branch \"{$branch->name}\" updated.");
    }

    public function toggle(Branch $branch): RedirectResponse
    {
        $this->authorize('update', $branch);
        $this->service->toggle($branch);

        $state = $branch->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$branch->name} {$state}.");
    }

    public function destroy(Branch $branch): RedirectResponse
    {
        $this->authorize('delete', $branch);
        $this->service->delete($branch);

        return redirect()->route('branches.index')->with('success', "Branch \"{$branch->name}\" deleted.");
    }
}
