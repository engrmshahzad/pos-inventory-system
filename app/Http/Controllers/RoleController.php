<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\RoleRequest;
use App\Models\Role;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Owner-only: custom roles plus the permissions of manager / cashier. The owner role is fixed. */
class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions:id,name')->withCount('users')->orderBy('id')->get();

        return view('users.roles', [
            'roles' => $roles,
            'groups' => Permissions::ALL,
            'ownerOnly' => Permissions::OWNER_ONLY,
            'baseTypes' => Role::BASE_TYPES,
            'granted' => $roles->mapWithKeys(fn ($role) => [$role->name => $role->permissions->pluck('name')->flip()]),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $role = Role::create(['name' => $request->input('name'), 'guard_name' => 'web', 'base_type' => $request->input('base_type')]);
        $role->syncPermissions($request->input('permissions'));

        activity('role_created', "Role \"{$role->name}\" created (".Role::BASE_TYPES[$role->base_type][0].', '.count($request->input('permissions')).' permissions)', $role);

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" created.");
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->name === 'owner', 403, 'The owner role always has every permission.');

        $before = $role->permissions->pluck('name');
        $after = collect($request->input('permissions'));
        $oldName = $role->name;

        $role->update(['name' => $request->input('name'), 'base_type' => $request->input('base_type')]);
        $role->syncPermissions($after->all());

        $changes = collect([
            $oldName !== $role->name ? "renamed from \"{$oldName}\"" : null,
            ($added = $after->diff($before))->isNotEmpty() ? 'added '.$added->implode(', ') : null,
            ($removed = $before->diff($after))->isNotEmpty() ? 'removed '.$removed->implode(', ') : null,
        ])->filter();
        activity('role_updated', "Role \"{$role->name}\": ".($changes->implode('; ') ?: 'no changes'), $role);

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" saved.");
    }

    public function reset(Role $role): RedirectResponse
    {
        $defaults = ['manager' => Permissions::forManager(), 'cashier' => Permissions::CASHIER][$role->name] ?? abort(404);
        $role->syncPermissions($defaults);
        activity('role_updated', "Role \"{$role->name}\" reset to default permissions", $role);

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" reset to its default permissions.");
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->isBuiltIn()) {
            throw ValidationException::withMessages(['action' => "\"{$role->name}\" is a built-in role and cannot be deleted."]);
        }
        if ($count = $role->users()->count()) {
            throw ValidationException::withMessages(['action' => "{$count} ".str('user')->plural($count)." still have the role \"{$role->name}\". Give them another role first."]);
        }

        $role->delete();
        activity('role_deleted', "Role \"{$role->name}\" deleted");

        return redirect()->route('roles.index')->with('success', "Role \"{$role->name}\" deleted.");
    }
}
