<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Marketplace-side records that share the merged database with logistics:
 * addresses, notifications, seller profiles, reviews, carts, favorites,
 * vouchers, legacy payments and SSO tokens.
 */
class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('MarketplaceDemoSeeder skipped outside local/testing.');

            return;
        }

        $buyers = DB::table('users')->where('role', 'buyer')->orderBy('id')->get();
        $adminId = DB::table('users')->where('role', 'admin')->value('id');
        $logisticsAdminId = DB::table('users')->where('role', 'logistics_admin')->value('id');

        $addresses = [
            ['buyer' => 'maria@invoiz.test', 'line' => '12 Rizal Street', 'barangay' => 'Barangay 1', 'city' => 'Manila', 'province' => 'Metro Manila', 'postal' => '1000', 'default' => true],
            ['buyer' => 'maria@invoiz.test', 'line' => '88 Mabini Avenue', 'barangay' => 'Poblacion', 'city' => 'Makati', 'province' => 'Metro Manila', 'postal' => '1200', 'default' => false],
            ['buyer' => 'juan@invoiz.test', 'line' => '34 Katipunan Road', 'barangay' => 'Batasan Hills', 'city' => 'Quezon City', 'province' => 'Metro Manila', 'postal' => '1126', 'default' => true],
            ['buyer' => 'juan@invoiz.test', 'line' => '7 Ortigas Center', 'barangay' => 'San Antonio', 'city' => 'Pasig', 'province' => 'Metro Manila', 'postal' => '1605', 'default' => false],
            ['buyer' => 'ana@invoiz.test', 'line' => '19 Osmena Boulevard', 'barangay' => 'Lahug', 'city' => 'Cebu City', 'province' => 'Cebu', 'postal' => '6000', 'default' => true],
            ['buyer' => 'pedro@invoiz.test', 'line' => '56 MacArthur Highway', 'barangay' => 'Del Pilar', 'city' => 'San Fernando', 'province' => 'Pampanga', 'postal' => '2000', 'default' => true],
            ['buyer' => 'liza@invoiz.test', 'line' => '3 Session Road', 'barangay' => 'Poblacion', 'city' => 'Tagbilaran', 'province' => 'Bohol', 'postal' => '6300', 'default' => true],
        ];

        foreach ($addresses as $address) {
            $buyer = DB::table('users')->where('email', $address['buyer'])->first();

            if (! $buyer) {
                continue;
            }

            DB::table('addresses')->updateOrInsert(
                ['buyer_id' => $buyer->id, 'address_line' => $address['line']],
                [
                    'recipient_name' => $buyer->name,
                    'phone' => $buyer->phone ?? '09170000000',
                    'barangay' => $address['barangay'],
                    'city' => $address['city'],
                    'province' => $address['province'],
                    'region' => $address['province'] === 'Cebu' ? 'Central Visayas (Region VII)' : ($address['province'] === 'Pampanga' ? 'Central Luzon (Region III)' : 'National Capital Region (NCR)'),
                    'postal_code' => $address['postal'],
                    'is_default' => $address['default'],
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $notifications = [
            ['type' => 'new_order', 'title' => 'New order received', 'message' => 'Order ORD-00002 is waiting for fulfillment.', 'icon' => 'shopping-cart', 'priority' => 'normal', 'is_read' => false, 'link' => '/seller/orders'],
            ['type' => 'delivery_completed', 'title' => 'Delivery completed', 'message' => 'TRK-20260819-0001 was delivered successfully.', 'icon' => 'check-circle', 'priority' => 'low', 'is_read' => false, 'link' => '/logistics/deliveries'],
            ['type' => 'failed_delivery', 'title' => 'Delivery failed', 'message' => 'TRK-20260819-0009 needs a reattempt.', 'icon' => 'alert-triangle', 'priority' => 'high', 'is_read' => false, 'link' => '/logistics/deliveries'],
            ['type' => 'new_message', 'title' => 'New logistics message', 'message' => 'Rider One sent you a message.', 'icon' => 'message', 'priority' => 'normal', 'is_read' => true, 'link' => '/logistics/messages'],
            ['type' => 'rider_application', 'title' => 'New rider application', 'message' => 'Pending Applicant submitted requirements.', 'icon' => 'truck', 'priority' => 'normal', 'is_read' => false, 'link' => '/logistics/rider-applications'],
            ['type' => 'system', 'title' => 'Scheduled maintenance', 'message' => 'The platform will be unavailable at 2 AM.', 'icon' => 'settings', 'priority' => 'low', 'is_read' => true, 'link' => null],
        ];

        foreach ($notifications as $notification) {
            DB::table('notifications')->updateOrInsert(
                ['type' => $notification['type'], 'title' => $notification['title']],
                [
                    'message' => $notification['message'],
                    'icon' => $notification['icon'],
                    'priority' => $notification['priority'],
                    'is_read' => $notification['is_read'],
                    'link' => $notification['link'],
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $notificationLogs = [
            ['type' => 'email', 'subject' => 'Order confirmation ORD-00002', 'body' => 'Your order has been confirmed.', 'email' => 'maria@invoiz.test', 'sent' => true],
            ['type' => 'email', 'subject' => 'Delivery out for delivery', 'body' => 'Your parcel is on the way.', 'email' => 'juan@invoiz.test', 'sent' => true],
            ['type' => 'sms', 'subject' => 'OTP verification', 'body' => 'Your verification code is 123456.', 'email' => null, 'sent' => true],
            ['type' => 'email', 'subject' => 'Rider application received', 'body' => 'We received your application.', 'email' => 'applicant1@test.com', 'sent' => false],
        ];

        foreach ($notificationLogs as $index => $log) {
            DB::table('notifications_log')->updateOrInsert(
                ['type' => $log['type'], 'subject' => $log['subject']],
                [
                    'user_id' => $buyers[$index % max($buyers->count(), 1)]->id ?? null,
                    'body' => $log['body'],
                    'email' => $log['email'],
                    'sent' => $log['sent'],
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $notificationKeys = ['orders:ORD-00002', 'deliveries:TRK-20260819-0001', 'applications:rider-1'];

        foreach ($buyers->take(2) as $buyer) {
            foreach ($notificationKeys as $key) {
                DB::table('notification_reads')->updateOrInsert(
                    ['user_id' => $buyer->id, 'notification_key' => $key],
                    ['created_at' => DB::raw('COALESCE(created_at, NOW())'), 'updated_at' => now()]
                );
            }
        }

        $sellers = DB::table('sellers')->orderBy('id')->get();

        foreach ($sellers as $seller) {
            $sellerUser = DB::table('users')->where('id', $seller->user_id)->first();
            $logo = "seller-assets/logo-{$seller->id}.png";
            $banner = "seller-assets/banner-{$seller->id}.png";
            Storage::disk('public')->put($logo, 'demo-logo');
            Storage::disk('public')->put($banner, 'demo-banner');

            DB::table('seller_profiles')->updateOrInsert(
                ['seller_id' => $seller->id],
                [
                    'logo_path' => $logo,
                    'banner_path' => $banner,
                    'description' => $seller->store_name.' sells quality products at fair prices.',
                    'contact_email' => $sellerUser?->email,
                    'contact_phone' => $sellerUser?->phone ?? '09170000000',
                    'address' => 'Warehouse, '.($sellerUser?->city ?? 'Manila'),
                    'business_info' => 'Registered online seller since 2024.',
                    'operating_hours' => 'Mon-Sat 8:00 AM - 6:00 PM',
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );

            $sellerNotifications = [
                ['title' => 'New order for '.$seller->store_name, 'type' => 'order', 'body' => 'You have a new order to process.', 'is_read' => false],
                ['title' => 'Low stock alert', 'type' => 'inventory', 'body' => 'One of your products is running low.', 'is_read' => true],
            ];

            foreach ($sellerNotifications as $notification) {
                DB::table('seller_notifications')->updateOrInsert(
                    ['seller_id' => $seller->id, 'title' => $notification['title']],
                    [
                        'order_id' => $seller->id + 1,
                        'product_id' => $seller->id,
                        'type' => $notification['type'],
                        'body' => $notification['body'],
                        'is_read' => $notification['is_read'],
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $policies = [
            ['slug' => 'terms-of-service', 'title' => 'Terms of Service', 'content' => 'These terms govern the use of the Invoiz platform.'],
            ['slug' => 'privacy-policy', 'title' => 'Privacy Policy', 'content' => 'We collect only the data required to fulfill orders and deliveries.'],
            ['slug' => 'returns-and-refunds', 'title' => 'Returns and Refunds', 'content' => 'Buyers may request a return within seven days of delivery.'],
            ['slug' => 'shipping-policy', 'title' => 'Shipping Policy', 'content' => 'Delivery timelines depend on the service area and parcel volume.'],
        ];

        foreach ($policies as $policy) {
            DB::table('platform_policies')->updateOrInsert(
                ['slug' => $policy['slug']],
                [
                    'title' => $policy['title'],
                    'content' => $policy['content'],
                    'is_active' => true,
                    'version' => 1,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $auditActions = [
            ['action' => 'login', 'entity' => 'user', 'entity_id' => $adminId, 'meta' => ['ip' => '127.0.0.1']],
            ['action' => 'approve_rider', 'entity' => 'rider', 'entity_id' => 1, 'meta' => ['note' => 'Verified documents']],
            ['action' => 'reject_center', 'entity' => 'center_application', 'entity_id' => 3, 'meta' => ['reason' => 'Invalid permit']],
            ['action' => 'archive_delivery', 'entity' => 'delivery', 'entity_id' => 12, 'meta' => ['note' => 'Completed']],
            ['action' => 'update_settings', 'entity' => 'user', 'entity_id' => $logisticsAdminId, 'meta' => ['section' => 'notifications']],
        ];

        foreach ($auditActions as $entry) {
            DB::table('audit_logs')->updateOrInsert(
                ['action' => $entry['action'], 'entity' => $entry['entity'], 'entity_id' => $entry['entity_id']],
                [
                    'user_id' => $adminId,
                    'meta' => json_encode($entry['meta']),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $orders = DB::table('orders')->orderBy('id')->limit(12)->get();

        foreach ($orders as $order) {
            DB::table('order_status_histories')->updateOrInsert(
                ['order_id' => $order->id, 'to_status' => 'new'],
                [
                    'from_status' => null,
                    'notes' => 'Order created.',
                    'metadata' => json_encode(['source' => 'seed']),
                    'created_at' => $order->created_at,
                    'updated_at' => now(),
                ]
            );

            if ($order->status !== 'new') {
                DB::table('order_status_histories')->updateOrInsert(
                    ['order_id' => $order->id, 'to_status' => $order->status],
                    [
                        'from_status' => 'new',
                        'notes' => 'Order moved to '.$order->status.'.',
                        'metadata' => json_encode(['source' => 'seed']),
                        'created_at' => $order->updated_at,
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $products = DB::table('products')->orderBy('id')->limit(20)->get();

        foreach ($products as $product) {
            foreach ([0, 1] as $index) {
                $path = "products/demo-{$product->id}-{$index}.jpg";
                Storage::disk('public')->put($path, 'demo-product-image');

                DB::table('product_images')->updateOrInsert(
                    ['product_id' => $product->id, 'path' => $path],
                    [
                        'image_path' => $path,
                        'is_main' => $index === 0,
                        'sort_order' => $index,
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        'updated_at' => now(),
                        'deleted_at' => null,
                    ]
                );
            }
        }

        $reviews = [
            ['buyer' => 'maria@invoiz.test', 'product' => 1, 'order' => 6, 'rating' => 5, 'comment' => 'Fast delivery and well packed.'],
            ['buyer' => 'juan@invoiz.test', 'product' => 2, 'order' => 14, 'rating' => 4, 'comment' => 'Good quality for the price.'],
            ['buyer' => 'ana@invoiz.test', 'product' => 3, 'order' => 16, 'rating' => 5, 'comment' => 'Exactly as described.'],
            ['buyer' => 'pedro@invoiz.test', 'product' => 4, 'order' => 23, 'rating' => 3, 'comment' => 'Delivery took longer than expected.'],
            ['buyer' => 'liza@invoiz.test', 'product' => 5, 'order' => 24, 'rating' => 5, 'comment' => 'Will order again.'],
        ];

        foreach ($reviews as $review) {
            $buyer = DB::table('users')->where('email', $review['buyer'])->first();

            if (! $buyer) {
                continue;
            }

            DB::table('reviews')->updateOrInsert(
                ['buyer_id' => $buyer->id, 'product_id' => $review['product'], 'order_id' => $review['order']],
                [
                    'rating' => $review['rating'],
                    'comment' => $review['comment'],
                    'status' => 'visible',
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $demoBuyer = $buyers->first();

        if ($demoBuyer) {
            DB::table('carts')->updateOrInsert(
                ['buyer_id' => $demoBuyer->id],
                [
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );

            $cart = DB::table('carts')->where('buyer_id', $demoBuyer->id)->first();

            if ($cart) {
                foreach ([1, 2, 3] as $productId) {
                    DB::table('cart_items')->updateOrInsert(
                        ['cart_id' => $cart->id, 'product_id' => $productId, 'variant_id' => null],
                        [
                            'quantity' => 1,
                            'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                            'updated_at' => now(),
                        ]
                    );
                }
            }

            foreach ([4, 5, 6, 7, 8] as $productId) {
                DB::table('favorites')->updateOrInsert(
                    ['buyer_id' => $demoBuyer->id, 'product_id' => $productId],
                    ['created_at' => now()]
                );
            }
        }

        $sellerUserIds = DB::table('users')->where('role', 'seller')->orderBy('id')->pluck('id');

        foreach ($buyers as $buyer) {
            foreach ($sellerUserIds->take(3) as $sellerUserId) {
                DB::table('store_follows')->updateOrInsert(
                    ['buyer_id' => $buyer->id, 'seller_id' => $sellerUserId],
                    ['created_at' => now()]
                );
            }
        }

        $inventories = DB::table('inventories')->orderBy('id')->limit(30)->get();

        foreach ($inventories as $index => $inventory) {
            DB::table('inventory_movements')->updateOrInsert(
                ['inventory_id' => $inventory->id, 'type' => 'stock_in', 'quantity' => 10],
                [
                    'meta' => json_encode(['source' => 'seed', 'note' => 'Initial stock']),
                    'created_at' => now()->subDays(10),
                    'updated_at' => now(),
                ]
            );

            DB::table('inventory_movements')->updateOrInsert(
                ['inventory_id' => $inventory->id, 'type' => 'sale', 'quantity' => -($index % 3 + 1)],
                [
                    'meta' => json_encode(['source' => 'seed', 'note' => 'Order fulfillment']),
                    'created_at' => now()->subDays(5),
                    'updated_at' => now(),
                ]
            );
        }

        $vouchers = DB::table('vouchers')->orderBy('id')->limit(3)->get();

        foreach ($buyers->take(5) as $index => $buyer) {
            foreach ($vouchers as $voucherIndex => $voucher) {
                DB::table('voucher_claims')->updateOrInsert(
                    ['buyer_id' => $buyer->id, 'voucher_id' => $voucher->id],
                    [
                        'order_id' => $index === 0 && $voucherIndex === 0 ? 2 : null,
                        'claimed_at' => now()->subDays(3),
                        'used_at' => $index === 0 && $voucherIndex === 0 ? now()->subDays(2) : null,
                        'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        foreach (DB::table('orders')->orderBy('id')->limit(10)->get() as $index => $order) {
            DB::table('payments')->updateOrInsert(
                ['order_id' => $order->id],
                [
                    'method' => ['cash_on_delivery', 'gcash', 'bank_transfer'][$index % 3],
                    'status' => match (true) {
                        $order->payment_status === 'paid' => 'paid',
                        $order->payment_status === 'refunded' => 'refunded',
                        $order->payment_status === 'failed' => 'failed',
                        default => 'pending',
                    },
                    'amount' => $order->total,
                    'reference_number' => 'PAY-'.str_pad((string) $order->id, 5, '0', STR_PAD_LEFT),
                    'paid_at' => $order->payment_status === 'paid' ? $order->updated_at : null,
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                    'updated_at' => now(),
                ]
            );
        }

        $orderVouchers = [
            ['order_id' => 2, 'voucher_id' => 1, 'discount' => 100],
            ['order_id' => 4, 'voucher_id' => 2, 'discount' => 50],
            ['order_id' => 6, 'voucher_id' => 3, 'discount' => 49],
        ];

        foreach ($orderVouchers as $orderVoucher) {
            DB::table('order_vouchers')->updateOrInsert(
                ['order_id' => $orderVoucher['order_id'], 'voucher_id' => $orderVoucher['voucher_id']],
                [
                    'discount_amount' => $orderVoucher['discount'],
                    'created_at' => now(),
                ]
            );
        }

        $ssoSuffix = substr(hash('sha256', (string) config('app.key')), 0, 32);

        DB::table('sso_tokens')->updateOrInsert(
            ['token' => 'demo-sso-valid-'.$ssoSuffix],
            [
                'user_id' => $demoBuyer->id ?? 2,
                'expires_at' => now()->addHours(2),
                'used_at' => null,
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );

        DB::table('sso_tokens')->updateOrInsert(
            ['token' => 'demo-sso-used-'.$ssoSuffix],
            [
                'user_id' => 2,
                'expires_at' => now()->addHours(2),
                'used_at' => now()->subHour(),
                'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                'updated_at' => now(),
            ]
        );
    }
}
