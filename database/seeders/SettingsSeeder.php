<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // General
            'store_name' => 'Invoiz',
            'store_email' => 'support@invoiz.test',
            'store_phone' => '+63 917 000 0000',
            'store_address' => 'Makati City, Metro Manila, Philippines',
            'currency' => 'PHP',
            'currency_symbol' => '₱',
            'timezone' => 'Asia/Manila',
            'language' => 'English',

            // Marketplace
            'seller_registration' => 'open',
            'auto_approve_sellers' => '0',
            'default_commission' => '10',
            'product_approval' => '1',
            'payout_minimum' => '500',
            'payout_schedule' => 'weekly',

            // Orders
            'allow_cancellations' => '1',
            'order_auto_cancel_hours' => '48',
            'allow_returns' => '1',
            'return_window_days' => '7',

            // Payments
            'pm_gcash' => '1',
            'pm_maya' => '1',
            'pm_card' => '1',
            'pm_cod' => '1',
            'gateway_mode' => 'sandbox',
            'auto_refund' => '0',
            'refund_window_days' => '14',

            // Delivery
            'base_delivery_fee' => '45',
            'free_shipping_threshold' => '1000',
            'zone_manila' => '45',
            'zone_luzon' => '80',
            'zone_visayas' => '120',
            'zone_mindanao' => '150',
            'auto_assign_rider' => '1',

            // Notifications
            'notif_email' => '1',
            'notif_sms' => '0',
            'notif_push' => '0',
            'sms_gateway' => 'none',

            // Security
            'session_timeout' => '60',
            'password_min_length' => '8',
            'password_require_symbols' => '1',
            'max_login_attempts' => '5',
            'two_factor' => '0',
        ];

        foreach ($defaults as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
