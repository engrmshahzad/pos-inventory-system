<?php

namespace App\Support;

/**
 * Every setting the app reads, grouped by Settings tab, with its default.
 * settings('key') falls back to these defaults when the row is missing.
 */
class SettingsDefinition
{
    public const GROUPS = [
        'general' => [
            'business_name' => 'NexaPOS Retail',
            'business_logo' => null,
            'business_favicon' => null,
            'business_phone' => '',
            'business_email' => '',
            'business_address' => '',
            'currency_symbol' => 'Rs',
            'number_format' => 'international', // international | indian
            'date_format' => 'd M Y',
            'timezone' => 'Asia/Karachi',
            'session_timeout' => '120', // minutes of inactivity before logout
        ],
        'pos' => [
            'receipt_size' => '80',
            'receipt_header' => '',
            'receipt_footer' => 'Thank you for shopping with us!',
            'receipt_show_logo' => '1',
            'allow_price_edit' => '1',
            'allow_negative_stock' => '0',
            'cashier_max_discount' => '5', // percent of the bill
            'default_customer_id' => null,
            'beep_on_scan' => '1',
        ],
        'tax' => [
            'tax_enabled' => '1',
            'tax_default_rate' => '0',
            'tax_mode' => 'exclusive', // exclusive | inclusive
        ],
        'alerts' => [
            'low_stock_alerts' => '1',
            'expiry_warning_days' => '30',
            'adjustment_approval_threshold' => '5000',
        ],
        'prefixes' => [
            'prefix_sale' => 'S',
            'prefix_purchase' => 'P',
            'prefix_sale_return' => 'SR',
            'prefix_purchase_return' => 'PR',
            'prefix_transfer' => 'TR',
            'prefix_adjustment' => 'ADJ',
            'prefix_stock_count' => 'CNT',
            'prefix_receipt' => 'RCV',
            'prefix_payment' => 'PAY',
            'prefix_refund' => 'RFD',
            'prefix_expense' => 'EXP',
        ],
    ];

    /** Internal sequence type => prefix setting key. */
    public const PREFIX_KEYS = [
        'S' => 'prefix_sale', 'P' => 'prefix_purchase', 'SR' => 'prefix_sale_return', 'PR' => 'prefix_purchase_return',
        'TR' => 'prefix_transfer', 'ADJ' => 'prefix_adjustment', 'CNT' => 'prefix_stock_count', 'RCV' => 'prefix_receipt',
        'PAY' => 'prefix_payment', 'RFD' => 'prefix_refund', 'EXP' => 'prefix_expense',
    ];

    public static function default(string $key): mixed
    {
        foreach (self::GROUPS as $items) {
            if (array_key_exists($key, $items)) {
                return $items[$key];
            }
        }

        return null;
    }

    public static function groupOf(string $key): string
    {
        foreach (self::GROUPS as $group => $items) {
            if (array_key_exists($key, $items)) {
                return $group;
            }
        }

        return 'general';
    }
}
