<?php

namespace Database\Seeders;

use App\Models\Customer;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $customers = [
            ['Ali Raza Traders', '0300-2345678', 'Hyderabad', 'Shop 4, Resham Gali', 50000, 4500],
            ['Usman General Store', '0301-3456789', 'Hyderabad', 'Latifabad Unit 7', 30000, 0],
            ['Muhammad Asif', '0302-4567890', 'Hyderabad', 'Qasimabad Phase 1', 0, 0],
            ['Fatima Bibi', '0303-5678901', 'Hyderabad', 'Hirabad', 10000, 1200],
            ['Zahid Hussain', '0311-6789012', 'Karachi', 'Gulshan-e-Iqbal Block 13', 25000, 0],
            ['Nadia Kanwal', '0312-7890123', 'Karachi', 'PECHS Block 2', 0, 0],
            ['Karachi Mart (Wholesale)', '0313-8901234', 'Karachi', 'Saddar, Empress Market', 150000, 18500],
            ['Bilal Ahmed', '0314-9012345', 'Karachi', 'North Nazimabad', 15000, 2300],
            ['Sana Malik', '0315-0123456', 'Karachi', 'DHA Phase 5', 0, 0],
            ['Imran Khan Brohi', '0321-1234567', 'Sukkur', 'Military Road', 20000, 0],
            ['Shazia Parveen', '0322-2345670', 'Sukkur', 'New Pind', 10000, 800],
            ['Sukkur Kiryana Store', '0323-3456701', 'Sukkur', 'Clock Tower Bazaar', 60000, 9200],
            ['Rashid Mehmood', '0324-4567012', 'Sukkur', 'Barrage Colony', 0, 0],
            ['Ayesha Siddiqa', '0331-5670123', 'Hyderabad', 'Defence Housing', 0, 0],
            ['Khalid Mehmood', '0332-6701234', 'Karachi', 'Malir Cantt', 20000, 0],
            ['Hira Noor', '0333-7012345', 'Hyderabad', 'Citizen Colony', 5000, 0],
            ['Tariq Jamil Stores', '0334-0123457', 'Sukkur', 'Station Road', 40000, 5600],
            ['Waqas Ali', '0335-1234560', 'Karachi', 'Korangi No. 2', 0, 0],
            ['Rubina Akhtar', '0336-2345601', 'Hyderabad', 'Paretabad', 8000, 650],
            ['Faraz Qureshi', '0345-3456012', 'Karachi', 'Clifton Block 4', 0, 0],
        ];

        foreach ($customers as [$name, $phone, $city, $address, $limit, $opening]) {
            Customer::firstOrCreate(['phone' => $phone], [
                'name' => $name,
                'city' => $city,
                'address' => $address,
                'credit_limit' => $limit,
                'opening_balance' => $opening,
                'balance' => $opening,
                'is_active' => true,
            ]);
        }
    }
}
