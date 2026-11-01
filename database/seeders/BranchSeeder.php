<?php

namespace Database\Seeders;

use App\Services\BranchService;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    public function run(BranchService $service): void
    {
        $branches = [
            ['name' => 'Hyderabad Main', 'code' => 'HYD', 'city' => 'Hyderabad', 'phone' => '022-2781234', 'email' => 'hyd@pos.test', 'address' => 'Shop 12, Saddar Bazaar, Hyderabad'],
            ['name' => 'Karachi Tariq Road', 'code' => 'KHI', 'city' => 'Karachi', 'phone' => '021-34567890', 'email' => 'khi@pos.test', 'address' => 'Plot 45, Tariq Road, PECHS, Karachi'],
            ['name' => 'Sukkur Clock Tower', 'code' => 'SUK', 'city' => 'Sukkur', 'phone' => '071-5612345', 'email' => 'suk@pos.test', 'address' => 'Near Clock Tower, Frere Road, Sukkur'],
        ];

        foreach ($branches as $data) {
            if (! Branch::withTrashed()->where('code', $data['code'])->exists()) {
                $service->create($data + ['is_active' => true]);
            }
        }
    }
}
