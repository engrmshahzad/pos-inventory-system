<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use App\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::flat() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web'], ['base_type' => 'owner'])->syncPermissions(Permission::all());

        // Defaults only for a new role: re-running the seeder must not undo changes made on the Roles screen
        // (use "Reset to default" there instead).
        foreach (['manager' => Permissions::forManager(), 'cashier' => Permissions::CASHIER] as $name => $defaults) {
            $role = Role::firstOrCreate(['name' => $name, 'guard_name' => 'web'], ['base_type' => $name]);
            if ($role->wasRecentlyCreated) {
                $role->syncPermissions($defaults);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
