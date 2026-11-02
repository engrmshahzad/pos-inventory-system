<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'general' => [
                'business_name' => 'NexaPOS Retail',
                'business_phone' => '+92 300 1234567',
                'business_email' => 'info@nexapos.pk',
                'business_address' => 'Hyderabad, Sindh, Pakistan',
                'currency_symbol' => 'Rs',
                'number_format' => 'international', // or "indian" (1,25,000)
                'date_format' => 'd M Y',
                'timezone' => 'Asia/Karachi',
            ],
            'pos' => [
                'allow_negative_stock' => '0',
                'receipt_size' => '80',            // 58 or 80 (mm)
                'receipt_header' => 'Quality products, fair prices',
                'receipt_footer' => 'Thank you for shopping with us! No return without receipt.',
                'beep_on_scan' => '1',
            ],
            'alerts' => [
                'adjustment_approval_threshold' => '5000', // manager adjustments above this (Rs, at cost) need owner approval
                'expiry_warning_days' => '30',
            ],
        ];

        foreach ($defaults as $group => $items) {
            foreach ($items as $key => $value) {
                Setting::firstOrCreate(['key' => $key], ['group' => $group, 'value' => $value]);
            }
        }
    }
}
