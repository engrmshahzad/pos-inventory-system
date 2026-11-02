<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::firstOrCreate(['email' => 'owner@pos.test'], [
            'name' => 'Ahmed Raza',
            'phone' => '0300-1112233',
            'password' => 'password',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $owner->syncRoles('owner');
        $owner->forceFill(['pin' => '2468'])->save(); // demo approval PIN

        $staff = [
            'HYD' => [['Bilal Memon', '0301-2223344'], ['Sana Qureshi', '0302-3334455']],
            'KHI' => [['Imran Siddiqui', '0321-4445566'], ['Ayesha Khan', '0322-5556677']],
            'SUK' => [['Faisal Shaikh', '0333-6667788'], ['Hina Abbasi', '0334-7778899']],
        ];

        foreach ($staff as $code => [$manager, $cashier]) {
            $branch = Branch::where('code', $code)->firstOrFail();
            $slug = strtolower($code);

            foreach (['manager' => $manager, 'cashier' => $cashier] as $role => [$name, $phone]) {
                $user = User::firstOrCreate(['email' => "{$role}.{$slug}@pos.test"], [
                    'branch_id' => $branch->id,
                    'name' => $name,
                    'phone' => $phone,
                    'password' => 'password',
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]);
                $user->syncRoles($role);
                if ($role === 'manager') {
                    $user->forceFill(['pin' => '2468'])->save(); // demo approval PIN
                }
            }
        }
    }
}
