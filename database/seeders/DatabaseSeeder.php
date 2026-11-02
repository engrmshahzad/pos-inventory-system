<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SettingsSeeder::class,
            BranchSeeder::class,
            UserSeeder::class,
            CatalogueSeeder::class,
            ProductSeeder::class,
            SupplierSeeder::class,
            PurchaseSeeder::class,
            CustomerSeeder::class,
            ExpenseSeeder::class,
            SalesSeeder::class,
            KhataSeeder::class,
            InventoryControlSeeder::class,
        ]);
    }
}
