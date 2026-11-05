<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\UserRequest;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(protected UserService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();
        $perPage = in_array((int) $request->input('per_page'), [10, 25, 50, 100], true) ? (int) $request->input('per_page') : 10;

        $users = $this->scopedQuery($actor)
            ->with(['branch:id,name,code', 'roles:id,name'])
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $term = '%'.$request->input('search').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($request->filled('role'), fn (Builder $q) => $q->role($request->input('role')))
            ->when($actor->isOwner() && $request->filled('branch'), fn (Builder $q) => $q->where('branch_id', $request->integer('branch')))
            ->when($request->input('status') === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($request->input('status') === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        $base = $this->scopedQuery($actor);

        return view('users.index', [
            'users' => $users,
            'branches' => $actor->isOwner() ? Branch::orderBy('name')->get(['id', 'name', 'code']) : collect([$actor->branch]),
            'roles' => Role::query()->when(! $actor->isOwner(), fn ($q) => $q->where('base_type', 'cashier'))->orderBy('id')->pluck('name')->all(),
            'stats' => [
                'total' => (clone $base)->count(),
                'active' => (clone $base)->where('is_active', true)->count(),
                'managers' => (clone $base)->role('manager')->count(),
                'cashiers' => (clone $base)->role('cashier')->count(),
            ],
        ]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        $user = $this->service->create($request->validated(), $request->file('avatar'));

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" created.");
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        $this->service->update($user, $request->validated(), $request->file('avatar'));

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" updated.");
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);
        abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate your own account.');

        $user->update(['is_active' => ! $user->is_active]);
        $state = $user->is_active ? 'activated' : 'deactivated';

        return back()->with('success', "{$user->name} {$state}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();

        return redirect()->route('users.index')->with('success', "User \"{$user->name}\" deleted.");
    }

    /**
     * Owner on "All Branches" sees everyone; owner on one branch sees that branch's staff (plus owners).
     * A manager sees only the staff of their own branch.
     */
    protected function scopedQuery(User $actor): Builder
    {
        $query = User::query();

        if ($actor->isOwner()) {
            if ($branchId = currentBranchId()) {
                $query->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'));
            }

            return $query;
        }

        return $query->where('branch_id', $actor->branch_id);
    }
}
