<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\PaymentActionController;
use App\Http\Controllers\Seller\ChatController;
use App\Models\{Cart, CartItem, Order, OrderItem, OrderStatusHistory, Payment, PaymentDispute, PaymentTransaction, Product, ProductSale, ReturnRequest, Sale, Seller, SellerPayout, User, WithdrawalRequest};
use App\Support\{OrderCancellation, Payments, Returns, Sales};
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // These fixtures never migrate or modify the configured application database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $tables = [
            'users' => 'name email password remember_token role is_admin first_name last_name status account_status approval_status email_verified_at center_id',
            'sellers' => 'user_id store_name business_name status approval_status deleted_at',
            'riders' => 'user_id status',
            'logistics_settings' => 'user_id',
            'login_histories' => 'user_id logged_in_at ip_address user_agent',
            'seller_profiles' => 'seller_id business_info deleted_at',
            'products' => 'seller_id category_id name price stock status deleted_at low_stock_threshold cart_additions',
            'categories' => 'name active',
            'product_variants' => 'product_id stock price_adjustment status variant_type variant_value deleted_at',
            'orders' => 'seller_id buyer_id address_id total total_amount status payment_status order_number deleted_at discount_total shipping_fee cancellation_reason commission_amount',
            'order_items' => 'order_id product_id product_variant_id seller_id product_name quantity price unit_price total_price',
            'order_status_histories' => 'order_id from_status to_status note notes',
            'deliveries' => 'order_id seller_id status tracking_number archived_at center_id picked_up_at cancelled_at cancellation_reason',
            'delivery_status_logs' => 'delivery_id status notes changed_by',
            'pickup_requests' => 'delivery_id center_id status requested_at reviewed_at',
            'return_requests' => 'order_id order_item_id buyer_id seller_id product_id quantity reason reason_details refund_amount status decision_note resolved_at',
            'payment_transactions' => 'order_id transaction_ref gateway amount status seller_earning platform_commission rider_fee logistics_fee refunded_amount paid_at',
            'payments' => 'order_id method amount status paid_at',
            'order_vouchers' => 'order_id voucher_id discount_amount',
            'vouchers' => 'seller_id code name discount_type type value discount_value used_count status min_spend max_discount',
            'voucher_claims' => 'voucher_id buyer_id order_id used_at',
            'settings' => 'key value',
            'conversations' => 'buyer_id seller_id subject',
            'messages' => 'conversation_id sender_id receiver_id body is_read',
            'sales' => 'seller_id name type discount_type discount_value starts_at ends_at status deleted_at',
            'product_sales' => 'sale_id product_id sale_price deleted_at',
            'carts' => 'buyer_id',
            'cart_items' => 'cart_id product_id variant_id quantity',
            'withdrawal_requests' => 'seller_id amount status method processed_at note',
            'seller_payouts' => 'seller_id amount status method paid_at',
            'payment_disputes' => 'order_id buyer_id seller_id amount status reason resolution_note resolved_at',
            'addresses' => 'buyer_id',
        ];
        foreach ($tables as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns): void {
                $table->id();
                foreach (explode(' ', $columns) as $column) {
                    if (str_ends_with($column, '_id') || in_array($column, ['quantity', 'stock', 'used_count', 'is_read', 'is_admin'])) {
                        $table->integer($column)->nullable();
                    } elseif (preg_match('/amount|price|total|fee|earning|commission|discount_value/', $column)) {
                        $table->decimal($column, 12, 2)->nullable();
                    } else {
                        $table->text($column)->nullable();
                    }
                }
                $table->timestamps();
            });
        }
        Schema::table('orders', fn (Blueprint $table) => $table->unique('order_number'));
        Schema::table('payment_transactions', fn (Blueprint $table) => $table->unique('transaction_ref'));
    }

    private function buyer(): User
    {
        return User::create(['name' => 'Test Buyer', 'email' => 'buyer@example.test', 'password' => 'Password123!', 'role' => 'buyer']);
    }

    private function purchase(int $buyerId = 201, string $status = 'delivered'): array
    {
        $product = Product::create(['seller_id' => 101, 'name' => 'Test item', 'price' => 100, 'stock' => 10, 'status' => 'active']);
        $order = Order::create(['buyer_id' => $buyerId, 'seller_id' => 101, 'status' => $status, 'total_amount' => 500, 'payment_status' => 'paid']);
        $item = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'seller_id' => 101, 'quantity' => 5, 'price' => 100]);
        Payment::create(['order_id' => $order->id, 'amount' => 500, 'method' => 'cash_on_delivery', 'status' => 'paid']);
        return [$product, $order, $item];
    }

    private function approvedReturn(Order $order, OrderItem $item, int $quantity = 1): ReturnRequest
    {
        return ReturnRequest::create(['order_id' => $order->id, 'order_item_id' => $item->id, 'product_id' => $item->product_id,
            'buyer_id' => $order->buyer_id, 'seller_id' => 101, 'quantity' => $quantity, 'refund_amount' => $quantity * 100,
            'reason' => 'wrong_item', 'status' => 'approved']);
    }

    public function test_delivery_repair_is_repeatable_and_preserves_existing_shipments(): void
    {
        DB::table('deliveries')->insert(['id' => 1, 'status' => 'waiting_for_rider']);
        $migration = require base_path('database/migrations/2026_10_26_000001_repair_delivery_workflow_columns.php');
        $migration->up();
        DB::table('deliveries')->where('id', 1)->update(['cod_amount_received' => 125]);
        $migration->up();
        foreach (['returned_at', 'return_reason', 'reattempt_requested_at', 'cod_amount_received', 'cod_received_at',
            'delivery_otp', 'delivery_otp_expires_at', 'delivery_otp_attempts', 'delivery_otp_consumed_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('deliveries', $column), $column);
        }
        $this->assertDatabaseHas('deliveries', ['id' => 1, 'status' => 'waiting_for_rider', 'cod_amount_received' => 125, 'delivery_otp_attempts' => 0]);
    }

    public function test_logistics_credentials_authenticate_and_redirect_to_dashboard(): void
    {
        foreach (['logistics_admin', 'logistics_staff'] as $role) {
            $user = User::create(['name' => 'Logistics Test', 'email' => $role.'@example.test', 'password' => 'Password123!', 'role' => $role]);
            $this->post('/logistics/login', ['email' => $user->email, 'password' => 'Password123!'])
                ->assertRedirect('/logistics/dashboard');
            $this->assertAuthenticatedAs($user);
            auth()->logout();
        }
    }

    public function test_rapid_order_creation_and_legacy_history_notes_work(): void
    {
        $this->travelTo(now()->startOfSecond());
        $numbers = [];
        for ($i = 0; $i < 10; $i++) {
            $numbers[] = Order::create(['total_amount' => 100])->order_number;
        }
        $this->assertCount(10, array_unique($numbers));
        $history = OrderStatusHistory::create(['order_id' => 1, 'to_status' => 'pending', 'note' => 'Order placed.']);
        $this->assertSame('Order placed.', $history->fresh()->notes);
        $this->assertSame('Order placed.', $history->fresh()->note);
        DB::table('order_status_histories')->where('id', $history->id)->update(['notes' => null, 'note' => 'Legacy note']);
        $this->assertSame('Legacy note', $history->fresh()->notes);
    }

    public function test_partial_return_restocks_only_returned_units_and_is_idempotent(): void
    {
        [$product, $order, $item] = $this->purchase();
        $return = $this->approvedReturn($order, $item);
        Returns::complete($return);
        Returns::complete($return);
        $this->assertSame(11, (int) $product->fresh()->stock);
        $this->assertSame('refunded', $return->fresh()->status);
        $this->assertSame(100.0, (float) Payments::transactionFor($order)->refunded_amount);
        $this->assertSame('partially_refunded', $order->fresh()->payment_status);
        $this->assertSame('partially_refunded', $order->payment()->first()->status);
    }

    public function test_partial_return_restores_the_purchased_variant_only(): void
    {
        [$product, $order, $item] = $this->purchase();
        $variantId = DB::table('product_variants')->insertGetId(['product_id' => $product->id, 'stock' => 3, 'status' => 'active']);
        $item->update(['product_variant_id' => $variantId]);
        Returns::complete($this->approvedReturn($order, $item, 2));
        $this->assertDatabaseHas('product_variants', ['id' => $variantId, 'stock' => 5]);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_refunds_are_capped_and_repeated_dispute_resolution_is_harmless(): void
    {
        [, $order] = $this->purchase();
        Payments::refund($order, 100);
        $dispute = PaymentDispute::create(['order_id' => $order->id, 'amount' => 500, 'status' => 'open']);
        $request = Request::create('/', 'POST', ['resolution' => 'refund_buyer']);
        $controller = new PaymentActionController;
        $controller->resolveDispute($request, $dispute->id);
        $controller->resolveDispute($request, $dispute->id);
        $this->assertSame(500.0, (float) Payments::transactionFor($order)->refunded_amount);
        $this->assertSame('refunded', $order->fresh()->payment_status);
        $this->assertSame(0.0, Payments::refund($order, 100));
        $this->assertSame('resolved', $dispute->fresh()->status);
    }

    public function test_failed_refund_does_not_complete_return_or_restore_stock(): void
    {
        [$product, $order, $item] = $this->purchase();
        $return = $this->approvedReturn($order, $item);
        Schema::drop('payment_transactions');
        try {
            Returns::complete($return);
            $this->fail('The unavailable ledger must fail the transaction.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('payment_transactions', $exception->getMessage());
        }
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame('approved', $return->fresh()->status);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_return_requests_allow_remaining_units_and_reject_cumulative_excess(): void
    {
        $buyer = $this->buyer();
        [$product, $order, $item] = $this->purchase($buyer->id);
        Returns::complete($this->approvedReturn($order, $item, 2));
        Sanctum::actingAs($buyer);
        $uri = '/api/orders/'.$order->id.'/returns';
        $data = ['product_id' => $product->id, 'reason' => 'wrong_item'];
        $this->postJson($uri, $data + ['quantity' => 4])->assertStatus(422);
        $response = $this->postJson($uri, $data + ['quantity' => 3])->assertCreated()->assertJsonPath('return.refund_amount', '300.00');
        $return = ReturnRequest::findOrFail($response->json('return.id'));
        $return->update(['status' => 'approved']);
        Returns::complete($return);
        $this->postJson($uri, $data + ['quantity' => 1])->assertStatus(422);
        $this->assertSame(15, (int) $product->fresh()->stock);
    }

    public function test_buyer_cancellation_closes_every_shipment_and_restores_voucher_once(): void
    {
        [$product, $order] = $this->purchase(201, 'pending');
        foreach ([1 => 'pending', 2 => 'approved'] as $id => $pickupStatus) {
            DB::table('deliveries')->insert(['id' => $id, 'order_id' => $order->id, 'seller_id' => 101, 'status' => 'assigned']);
            DB::table('pickup_requests')->insert(['delivery_id' => $id, 'status' => $pickupStatus]);
        }
        DB::table('vouchers')->insert(['id' => 1, 'used_count' => 1]);
        DB::table('order_vouchers')->insert(['order_id' => $order->id, 'voucher_id' => 1]);
        DB::table('voucher_claims')->insert(['voucher_id' => 1, 'buyer_id' => 201, 'order_id' => $order->id, 'used_at' => now()]);
        $this->assertTrue(OrderCancellation::byBuyer($order, 'Changed my mind', 201));
        $this->assertFalse(OrderCancellation::byBuyer($order, 'Changed my mind', 201));
        $this->assertSame(15, (int) $product->fresh()->stock);
        $this->assertSame(2, DB::table('deliveries')->where('status', 'cancelled')->count());
        $this->assertDatabaseHas('pickup_requests', ['delivery_id' => 1, 'status' => 'completed']);
        $this->assertDatabaseHas('pickup_requests', ['delivery_id' => 2, 'status' => 'approved']);
        $this->assertDatabaseHas('voucher_claims', ['voucher_id' => 1, 'order_id' => null, 'used_at' => null]);
        $this->assertDatabaseHas('vouchers', ['id' => 1, 'used_count' => 0]);
        $this->assertSame('Cancelled by buyer: Changed my mind', OrderStatusHistory::first()->notes);
    }

    public function test_order_cancellation_refuses_an_already_collected_parcel(): void
    {
        [$product, $order] = $this->purchase(201, 'processing');
        DB::table('deliveries')->insert(['order_id' => $order->id, 'status' => 'picked_up', 'picked_up_at' => now()]);
        try {
            OrderCancellation::byBuyer($order, 'Changed my mind', 201);
            $this->fail('Picked-up deliveries cannot be cancelled by buyers.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('order', $exception->errors());
        }
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock);
    }

    public function test_cancellation_rolls_back_shipment_and_stock_if_ledger_is_unavailable(): void
    {
        [$product, $order] = $this->purchase(201, 'pending');
        DB::table('deliveries')->insert(['id' => 1, 'order_id' => $order->id, 'status' => 'waiting_for_rider']);
        DB::table('pickup_requests')->insert(['delivery_id' => 1, 'status' => 'pending']);
        Schema::drop('payment_transactions');
        try {
            OrderCancellation::byBuyer($order, 'Changed my mind', 201);
            $this->fail('The cancellation must fail with its payment ledger.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('payment_transactions', $exception->getMessage());
        }
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertDatabaseHas('deliveries', ['id' => 1, 'status' => 'waiting_for_rider']);
        $this->assertDatabaseHas('pickup_requests', ['delivery_id' => 1, 'status' => 'pending']);
        $this->assertDatabaseCount('order_status_histories', 0);
    }

    public function test_return_refund_uses_the_discounted_purchase_total(): void
    {
        $buyer = $this->buyer();
        [$product, $order] = $this->purchase($buyer->id);
        DB::table('vouchers')->insert(['id' => 1, 'discount_type' => 'fixed', 'seller_id' => 101]);
        DB::table('order_vouchers')->insert(['order_id' => $order->id, 'voucher_id' => 1, 'discount_amount' => 50]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/orders/'.$order->id.'/returns', ['product_id' => $product->id, 'reason' => 'wrong_item', 'quantity' => 1])
            ->assertCreated()->assertJsonPath('return.refund_amount', '90.00');
    }

    public function test_sales_and_api_cart_share_prices_across_both_seller_ids(): void
    {
        $buyer = $this->buyer();
        [$product] = $this->purchase($buyer->id);
        DB::table('sellers')->insert(['id' => 5, 'user_id' => 101]);
        $sale = Sale::create(['seller_id' => 5, 'name' => 'Sale', 'type' => 'store', 'discount_type' => 'percent', 'discount_value' => 20, 'status' => 'active']);
        ProductSale::create(['sale_id' => $sale->id, 'product_id' => $product->id]);
        $cart = Cart::create(['buyer_id' => $buyer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->assertSame(80.0, Sales::priceFor($product));
        Sanctum::actingAs($buyer);
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('cart.subtotal', 160)->assertJsonPath('cart.items.0.unit_price', 80);
    }

    public function test_new_cart_routes_enforce_ownership_and_clear_only_own_items(): void
    {
        $buyer = $this->buyer();
        [$product] = $this->purchase($buyer->id);
        $cart = Cart::create(['buyer_id' => $buyer->id]);
        $otherCart = Cart::create(['buyer_id' => 900]);
        $own = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $other = CartItem::create(['cart_id' => $otherCart->id, 'product_id' => $product->id, 'quantity' => 1]);
        Sanctum::actingAs($buyer);
        $this->patchJson('/api/cart/'.$own->id, ['quantity' => 3])->assertOk();
        $this->patchJson('/api/cart/'.$other->id, ['quantity' => 2])->assertNotFound();
        $this->deleteJson('/api/cart/'.$other->id)->assertNotFound();
        $this->deleteJson('/api/cart/'.$own->id)->assertOk();
        CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->deleteJson('/api/cart')->assertOk();
        $this->assertDatabaseHas('cart_items', ['id' => $other->id, 'quantity' => 1]);
        $this->assertSame(0, $cart->items()->count());
    }

    public function test_order_detail_route_is_accessible_only_to_its_buyer(): void
    {
        $buyer = $this->buyer();
        [, $own] = $this->purchase($buyer->id);
        [, $other] = $this->purchase(900);
        Sanctum::actingAs($buyer);
        $this->getJson('/api/orders/'.$own->id)->assertOk()->assertJsonPath('order.id', $own->id);
        $this->getJson('/api/orders/'.$other->id)->assertNotFound();
    }

    public function test_seller_inbox_excludes_other_stores_but_preserves_explicit_legacy_participation(): void
    {
        $seller = User::create(['name' => 'Seller A', 'email' => 'seller@example.test', 'role' => 'seller']);
        Seller::create(['user_id' => $seller->id, 'store_name' => 'Seller A', 'status' => 'approved', 'approval_status' => 'approved']);
        [, $order] = $this->purchase(201);
        $order->items()->update(['seller_id' => $seller->id]);
        DB::table('conversations')->insert([
            ['id' => 1, 'buyer_id' => 201, 'seller_id' => 102],
            ['id' => 2, 'buyer_id' => 201, 'seller_id' => $seller->id],
            ['id' => 3, 'buyer_id' => 201, 'seller_id' => null],
            ['id' => 4, 'buyer_id' => 201, 'seller_id' => null],
        ]);
        DB::table('messages')->insert(['conversation_id' => 3, 'sender_id' => 201, 'receiver_id' => $seller->id, 'body' => 'Legacy store thread']);
        $this->actingAs($seller)->withSession(['active_role' => 'seller']);
        $ids = (new ChatController)->index()->getData()['conversations']->pluck('id')->sort()->values()->all();
        $this->assertSame([2, 3], $ids);
        $this->get('/seller/chat/1')->assertNotFound();
        $this->post('/seller/chat/1/reply', ['body' => 'Forbidden'])->assertNotFound();
    }

    public function test_withdrawal_approval_is_atomic_and_idempotent(): void
    {
        $seller = Seller::create(['user_id' => 101, 'store_name' => 'Test store']);
        $withdrawal = WithdrawalRequest::create(['seller_id' => $seller->id, 'amount' => 100, 'status' => 'pending', 'method' => 'GCash']);
        $controller = new PaymentActionController;
        $controller->approveWithdrawal($withdrawal->id);
        $controller->approveWithdrawal($withdrawal->id);
        $this->assertSame(1, SellerPayout::count());
        $this->assertSame('approved', $withdrawal->fresh()->status);
        $second = WithdrawalRequest::create(['seller_id' => $seller->id, 'amount' => 100, 'status' => 'pending', 'method' => 'GCash']);
        Schema::drop('seller_payouts');
        try {
            $controller->approveWithdrawal($second->id);
            $this->fail('An unavailable payout table must fail approval.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('seller_payouts', $exception->getMessage());
        }
        $this->assertSame('pending', $second->fresh()->status);
    }

    public function test_payout_requests_enforce_available_balance_and_one_pending_request(): void
    {
        $user = User::forceCreate(['id' => 101, 'name' => 'Seller', 'email' => 'seller@example.test', 'role' => 'seller']);
        $seller = Seller::create(['user_id' => $user->id, 'store_name' => 'Store', 'status' => 'approved', 'approval_status' => 'approved']);
        [, $order] = $this->purchase();
        PaymentTransaction::create(['order_id' => $order->id, 'transaction_ref' => 'TXN-ORDER-'.$order->id, 'amount' => 500, 'status' => 'successful']);
        $this->actingAs($user)->withSession(['active_role' => 'seller']);
        $this->postJson('/seller/payouts/request', ['amount' => 1000, 'method' => 'GCash'])->assertStatus(422);
        $this->postJson('/seller/payouts/request', ['amount' => 100, 'method' => 'GCash'])->assertRedirect();
        $this->postJson('/seller/payouts/request', ['amount' => 100, 'method' => 'GCash'])->assertStatus(422);
        $this->assertSame(1, WithdrawalRequest::count());
        (new PaymentActionController)->approveWithdrawal(WithdrawalRequest::first()->id);
        $this->assertSame(335.0, Payments::availableBalanceForUser($user->id));
        $this->assertDatabaseHas('seller_payouts', ['seller_id' => $seller->id, 'amount' => 100]);
    }

    public function test_suspended_web_buyer_session_is_revoked_before_cart_mutation(): void
    {
        $buyer = $this->buyer();
        [$product] = $this->purchase($buyer->id);
        $buyer->update(['account_status' => 'suspended']);
        $this->actingAs($buyer)->withSession(['buyer' => ['id' => $buyer->id]]);
        $this->post('/cart/add/'.$product->id)->assertRedirect('/login');
        $this->assertGuest();
        $this->assertDatabaseCount('cart_items', 0);
        $this->get('/login')->assertOk();
    }

    public function test_cart_get_requests_cannot_change_quantity(): void
    {
        $buyer = $this->buyer();
        [$product] = $this->purchase($buyer->id);
        $cart = Cart::create(['buyer_id' => $buyer->id]);
        $item = CartItem::create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2]);
        $this->actingAs($buyer)->withSession(['buyer' => ['id' => $buyer->id]]);
        foreach (['add/'.$product->id, 'inc/'.$item->id, 'dec/'.$item->id, 'remove/'.$item->id] as $path) {
            $this->get('/cart/'.$path)->assertStatus(405);
        }
        $this->assertSame(2, (int) $item->fresh()->quantity);
        $this->post('/cart/inc/'.$item->id)->assertRedirect();
        $this->assertSame(3, (int) $item->fresh()->quantity);
    }
}
