<?php

namespace Database\Seeders;

use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::flat() as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'owner', 'guard_name' => 'web'])->syncPermissions(Permission::all());
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'web'])->syncPermissions(Permissions::forManager());
        Role::firstOrCreate(['name' => 'cashier', 'guard_name' => 'web'])->syncPermissions(Permissions::CASHIER);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
