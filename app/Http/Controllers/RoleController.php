<?php

namespace App\Http\Controllers;

use App\Support\Permissions;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(): View
    {
        $roles = Role::with('permissions:id,name')->withCount('users')->orderBy('id')->get();

        return view('users.roles', [
            'roles' => $roles,
            'groups' => Permissions::ALL,
            'granted' => $roles->mapWithKeys(fn ($role) => [$role->name => $role->permissions->pluck('name')->flip()]),
        ]);
    }
}
