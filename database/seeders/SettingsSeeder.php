<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = \App\Support\SettingsDefinition::GROUPS;
        $defaults['general'] = array_merge($defaults['general'], [
            'business_phone' => '+92 300 1234567',
            'business_email' => 'info@nexapos.pk',
            'business_address' => 'Hyderabad, Sindh, Pakistan',
        ]);
        $defaults['pos']['receipt_header'] = 'Quality products, fair prices';
        $defaults['pos']['receipt_footer'] = 'Thank you for shopping with us! No return without receipt.';

        foreach ($defaults as $group => $items) {
            foreach ($items as $key => $value) {
                Setting::firstOrCreate(['key' => $key], ['group' => $group, 'value' => $value]);
            }
        }
    }
}
