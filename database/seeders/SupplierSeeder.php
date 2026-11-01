<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        $suppliers = [
            ['Kamran Ali', 'Tapal Tea Distributors', '0300-8001001', 'Karachi', 'SITE Area, Karachi', '1234567-8', 25000],
            ['Shahid Mehmood', 'Nestlé Pakistan (Hyderabad Dist.)', '0301-8002002', 'Hyderabad', 'Auto Bhan Road, Hyderabad', '2345678-9', 0],
            ['Asad Khan', 'Shan Foods Wholesale', '0302-8003003', 'Karachi', 'Korangi Industrial Area', '3456789-0', 12000],
            ['Naveed Akhtar', 'Unilever Sindh Distribution', '0321-8004004', 'Karachi', 'Port Qasim', '4567890-1', 0],
            ['Rizwan Qureshi', 'Dalda Foods Dealer', '0322-8005005', 'Hyderabad', 'Latifabad Unit 10', null, 18000],
            ['Saleem Baloch', 'Olpers / Engro Foods Depot', '0333-8006006', 'Sukkur', 'Bypass Road, Sukkur', null, 0],
            ['Javed Iqbal', 'Peek Freans Biscuits Agency', '0334-8007007', 'Hyderabad', 'Market Tower, Hyderabad', null, 0],
            ['Farhan Siddiqui', 'Audionic Electronics Dealer', '0345-8008008', 'Karachi', 'Saddar Electronics Market', '5678901-2', 35000],
            ['Abdul Ghani', 'Sindh Rice & Flour Mills', '0346-8009009', 'Sukkur', 'Rohri Road', null, 0],
            ['Mushtaq Ahmed', 'Coca-Cola Bottlers (Hyd)', '0347-8010010', 'Hyderabad', 'SITE Hyderabad', '6789012-3', 8000],
        ];

        foreach ($suppliers as [$name, $company, $phone, $city, $address, $ntn, $opening]) {
            Supplier::firstOrCreate(['company' => $company], [
                'name' => $name, 'phone' => $phone, 'city' => $city, 'address' => $address, 'ntn' => $ntn,
                'opening_balance' => $opening, 'balance' => $opening, 'is_active' => true,
            ]);
        }
    }
}
