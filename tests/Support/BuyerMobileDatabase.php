<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

trait BuyerMobileDatabase
{
    protected function createMobileDatabase(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'session.driver' => 'array', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Mail::fake();
        $tables = [
            'users' => 'name first_name last_name email password role status account_status approval_status is_admin email_verified_at remember_token profile_picture phone address_line barangay city municipality province region postal_code',
            'categories' => 'name slug active status parent_id deleted_at',
            'products' => 'seller_id category_id name slug description price compare_at_price stock status deleted_at views_count cart_additions rating image brand',
            'product_variants' => 'product_id stock price_adjustment status variant_type variant_value deleted_at',
            'sellers' => 'user_id store_name business_name status approval_status deleted_at vacation_mode address_line barangay city province',
            'seller_profiles' => 'seller_id business_info deleted_at',
            'logistics_settings' => 'user_id',
            'carts' => 'buyer_id',
            'cart_items' => 'cart_id product_id variant_id quantity',
            'favorites' => 'buyer_id product_id',
            'reviews' => 'buyer_id product_id order_id rating comment status seller_reply',
            'addresses' => 'buyer_id recipient_name phone address_line barangay city province region postal_code is_default',
            'orders' => 'seller_id buyer_id address_id total total_amount sub_total discount_total shipping_fee shipping_address status payment_status order_number deleted_at',
            'order_items' => 'order_id product_id product_variant_id seller_id product_name variant_label quantity price unit_price total_price',
            'order_status_histories' => 'order_id from_status to_status note notes',
            'deliveries' => 'order_id seller_id status payment_method amount_to_collect delivery_fee tracking_number archived_at',
            'payments' => 'order_id method amount status',
            'payment_transactions' => 'order_id transaction_ref gateway amount status seller_earning platform_commission rider_fee logistics_fee refunded_amount paid_at',
            'vouchers' => 'seller_id code name discount_type type value discount_value used_count status min_spend max_discount usage_limit starts_at ends_at',
            'voucher_claims' => 'voucher_id buyer_id order_id claimed_at used_at',
            'order_vouchers' => 'order_id voucher_id discount_amount redemption_order_id',
            'conversations' => 'buyer_id seller_id subject',
            'messages' => 'conversation_id sender_id receiver_id body is_read',
            'complaints' => 'buyer_id subject status resolution deleted_at',
            'notification_reads' => 'user_id notification_key',
            'settings' => 'key value',
            'sales' => 'seller_id name type discount_type discount_value starts_at ends_at status deleted_at',
            'product_sales' => 'sale_id product_id sale_price deleted_at',
        ];
        $tables['users'] .= ' middle_initial sex birthday age id_image bio otp otp_expires_at center_id';
        $tables['sellers'] .= ' line_of_business seller_type tin region postal_code terms_accepted rejection_reason id_image dti_certificate bir_2303 business_permit logo';
        $tables['seller_profiles'] .= ' contact_email contact_phone address';
        $tables['products'] .= ' low_stock_threshold';
        $tables['orders'] .= ' notes cancellation_reason commission_amount';
        $tables['deliveries'] .= ' center_id rider_id picked_up_at cancelled_at cancellation_reason delivered_at parcel_status sender_name sender_phone sender_address recipient_name recipient_phone recipient_address notes';
        $tables['vouchers'] .= ' description valid_from valid_until';
        $tables += [
            'product_images' => 'product_id path image_path sort_order is_main deleted_at',
            'store_follows' => 'buyer_id seller_id',
            'personal_access_tokens' => 'tokenable_type tokenable_id name token abilities last_used_at expires_at',
            'delivery_items' => 'delivery_id name variant_label quantity price',
            'pickup_requests' => 'delivery_id center_id status requested_at reviewed_at',
            'notifications' => 'type title message icon priority center_id audience link',
            'login_histories' => 'user_id logged_in_at ip_address user_agent',
            'delivery_status_logs' => 'delivery_id status notes changed_by',
            'return_requests' => 'order_id order_item_id buyer_id seller_id product_id quantity status refund_amount reason reason_details',
        ];
        foreach ($tables as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach (explode(' ', $columns) as $column) {
                    if (str_ends_with($column, '_id') || in_array($column, ['stock', 'quantity', 'is_admin', 'is_default', 'active', 'vacation_mode', 'used_count', 'usage_limit', 'is_read', 'age', 'sort_order', 'is_main'])) {
                        $table->integer($column)->nullable();
                    } elseif (preg_match('/amount|price|total|fee|earning|commission|discount_value|adjustment/', $column)) {
                        $table->decimal($column, 12, 2)->nullable();
                    } else {
                        $table->text($column)->nullable();
                    }
                }
                $table->timestamps();
            });
        }
        Schema::table('carts', fn (Blueprint $t) => $t->unique('buyer_id'));
    }
}
