<?php

namespace Tests\Feature;

use App\Models\{Address, Cart, CartItem, Delivery, Order, Product, Seller, User, Voucher, VoucherClaim};
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class CheckoutVoucherScopeTest extends TestCase
{
    use BuyerMobileDatabase;

    private User $buyer;
    private Address $address;
    private Product $a;
    private Product $b;
    private CartItem $lineA;
    private CartItem $lineB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        $this->buyer = $this->account('buyer');
        $this->address = Address::create(['buyer_id' => $this->buyer->id, 'recipient_name' => 'Test Buyer', 'phone' => '09171234567',
            'address_line' => '2 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_default' => true]);
        $this->a = $this->product('a', 100);
        $this->b = $this->product('b', 900);
        $cart = Cart::headerFor($this->buyer->id);
        $this->lineA = CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->a->id, 'quantity' => 1]);
        $this->lineB = CartItem::create(['cart_id' => $cart->id, 'product_id' => $this->b->id, 'quantity' => 1]);
    }

    private function account(string $name): User
    {
        return User::create(['name' => $name, 'email' => $name.'@example.test', 'password' => 'Password123!', 'role' => 'buyer',
            'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function product(string $name, float $price): Product
    {
        $user = $this->account($name);
        $user->update(['role' => 'seller']);
        $seller = Seller::create(['user_id' => $user->id, 'store_name' => 'Store '.$name, 'status' => 'active', 'approval_status' => 'approved',
            'vacation_mode' => false, 'address_line' => '1 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila']);
        DB::table('sellers')->where('id', $seller->id)->update(['id' => 1000 + $user->id]);

        return Product::create(['seller_id' => $user->id, 'name' => 'Product '.$name, 'price' => $price, 'stock' => 10, 'status' => 'active']);
    }

    private function voucher(?Product $product = null, array $attributes = []): Voucher
    {
        return Voucher::create($attributes + ['seller_id' => $product ? Seller::where('user_id', $product->seller_id)->value('id') : null,
            'code' => 'TEST'.(Voucher::count() + 1), 'name' => 'Offer '.(Voucher::count() + 1), 'discount_type' => 'fixed',
            'discount_value' => 10, 'min_spend' => 0, 'status' => 'active', 'used_count' => 0]);
    }

    private function claim(Voucher $voucher): VoucherClaim
    {
        return VoucherClaim::create(['buyer_id' => $this->buyer->id, 'voucher_id' => $voucher->id, 'claimed_at' => now()]);
    }

    private function checkoutData(array $extra = []): array
    {
        return $extra + ['address_id' => $this->address->id, 'payment_method' => 'cash_on_delivery'];
    }

    public function test_preview_lists_only_usable_vouchers_for_selected_stores(): void
    {
        $store = $this->voucher($this->a);
        $platform = $this->voucher();
        $this->voucher($this->b);
        $this->voucher($this->a, ['min_spend' => 150]);
        $this->voucher($this->a, ['status' => 'inactive']);
        $this->voucher($this->a, ['ends_at' => now()->subDay()]);
        $this->voucher($this->a, ['usage_limit' => 1, 'used_count' => 1]);
        $used = $this->claim($this->voucher($this->a));
        $used->update(['used_at' => now()]);
        Sanctum::actingAs($this->buyer);

        $response = $this->postJson('/api/orders/preview', ['cart_item_ids' => [$this->lineA->id]])->assertOk();
        $this->assertEqualsCanonicalizing([$store->id, $platform->id], array_column($response->json('vouchers'), 'id'));
        $response->assertJsonPath('vouchers.0.checkout_savings', 10)->assertJsonMissingPath('vouchers.0.code');
        // Browsing/collecting offers remains available outside checkout.
        $this->getJson('/api/vouchers')->assertOk()->assertJsonCount(5, 'vouchers');
    }

    public function test_foreign_store_vouchers_are_rejected_on_preview_checkout_and_legacy_validation(): void
    {
        Sanctum::actingAs($this->buyer);
        foreach (['fixed', 'percent', 'free_shipping'] as $type) {
            $voucher = $this->voucher($this->b, ['discount_type' => $type]);
            $claim = $this->claim($voucher);
            $data = ['cart_item_ids' => [$this->lineA->id], 'voucher_claim_id' => $claim->id,
                'seller_id' => $this->b->seller_id, 'subtotal' => 999999];
            $this->postJson('/api/orders/preview', $data)->assertUnprocessable();
            $this->postJson('/api/orders/checkout', $this->checkoutData($data))->assertUnprocessable();
            $this->postJson('/api/vouchers/validate', $data + ['code' => $voucher->code])->assertUnprocessable();
            $this->assertNull($claim->fresh()->used_at);
            $this->assertEquals(0, $voucher->fresh()->used_count);
        }
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('deliveries', 0);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertEquals(10, $this->a->fresh()->stock);
    }

    public function test_another_stores_items_cannot_meet_a_store_vouchers_minimum_spend(): void
    {
        $voucher = $this->voucher($this->a, ['min_spend' => 150, 'discount_type' => 'percent', 'discount_value' => 20]);
        $claim = $this->claim($voucher);
        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/orders/preview')->assertOk()->assertJsonCount(0, 'vouchers');
        $this->postJson('/api/orders/preview', ['voucher_claim_id' => $claim->id])->assertUnprocessable();
        $this->postJson('/api/orders/checkout', $this->checkoutData(['voucher_claim_id' => $claim->id]))->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertNull($claim->fresh()->used_at);
    }

    public function test_mixed_cart_discounts_and_shipping_stay_with_the_issuing_store(): void
    {
        $merch = $this->claim($this->voucher($this->b, ['discount_type' => 'percent', 'discount_value' => 20]));
        $shipping = $this->claim($this->voucher($this->b, ['discount_type' => 'free_shipping', 'discount_value' => 0]));
        Sanctum::actingAs($this->buyer);
        $data = ['voucher_claim_ids' => [$merch->id, $shipping->id]];
        $preview = $this->postJson('/api/orders/preview', $data)->assertOk();
        $preview->assertJsonPath('quote.discount', 180)->assertJsonPath('quote.shipping_fee', 50)->assertJsonPath('quote.total', 870);
        $savings = array_column($preview->json('vouchers'), 'checkout_savings', 'id');
        $this->assertEquals(180, $savings[$merch->voucher_id]);
        $this->assertEquals(50, $savings[$shipping->voucher_id]);
        $id = $this->postJson('/api/orders/checkout', $this->checkoutData($data))->assertCreated()->json('order.id');
        $order = Order::where('seller_id', $this->b->seller_id)->firstOrFail();
        $this->assertEquals(0, $order->voucherDiscountForSeller($this->a->seller_id));
        $this->assertEquals(180, $order->voucherDiscountForSeller($this->b->seller_id));
        $this->assertEquals(0, $order->shippingWaivedForSeller($this->a->seller_id));
        $this->assertEquals(50, $order->shippingWaivedForSeller($this->b->seller_id));
        $this->assertDatabaseHas('deliveries', ['seller_id' => $this->a->seller_id, 'amount_to_collect' => 150]);
        $this->assertDatabaseHas('deliveries', ['seller_id' => $this->b->seller_id, 'amount_to_collect' => 720]);
        $this->assertEquals($order->total_amount, Delivery::where('order_id', $order->id)->sum('amount_to_collect'));
    }

    public function test_stacked_discounts_are_capped_and_platform_money_balances_after_store_discount(): void
    {
        $store = $this->claim($this->voucher($this->a, ['discount_value' => 200]));
        $platform = $this->claim($this->voucher(null, ['discount_value' => 999]));
        Sanctum::actingAs($this->buyer);
        $data = ['voucher_claim_ids' => [$store->id, $platform->id]];
        $this->postJson('/api/orders/preview', $data)->assertOk()->assertJsonPath('quote.discount', 1000)->assertJsonPath('quote.total', 100);
        $id = $this->postJson('/api/orders/checkout', $this->checkoutData($data))->assertCreated()->json('order.id');
        $orders = Order::where('buyer_id', $this->buyer->id)->get();
        $this->assertEquals(1000, $orders->sum(fn ($order) => $order->voucherDiscountTotal()));
        $this->assertEquals(100, $orders->firstWhere('seller_id', $this->a->seller_id)->voucherDiscountForSeller($this->a->seller_id));
        $this->assertEquals(900, $orders->firstWhere('seller_id', $this->b->seller_id)->voucherDiscountForSeller($this->b->seller_id));
        $this->assertEquals(100, Delivery::whereIn('order_id', $orders->modelKeys())->sum('amount_to_collect'));
    }

    public function test_seller_record_and_user_id_collisions_cannot_apply_another_stores_voucher(): void
    {
        DB::table('sellers')->where('user_id', $this->b->seller_id)->update(['id' => $this->a->seller_id]);
        $voucher = $this->voucher($this->b);
        $claim = $this->claim($voucher);
        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/orders/preview', ['cart_item_ids' => [$this->lineA->id]])->assertOk()->assertJsonCount(0, 'vouchers');
        $this->postJson('/api/orders/preview', ['cart_item_ids' => [$this->lineA->id], 'voucher_claim_id' => $claim->id])->assertUnprocessable();
        $this->postJson('/api/orders/preview', ['cart_item_ids' => [$this->lineB->id], 'voucher_claim_id' => $claim->id])->assertOk()->assertJsonPath('quote.discount', 10);
        $this->actingAs($this->buyer)->get('/checkout?seller='.$this->a->seller_id)->assertOk()->assertViewHas('claimedVouchers', fn ($claims) => $claims->isEmpty());
    }

    public function test_website_filters_vouchers_in_every_checkout_mode(): void
    {
        $a = $this->claim($this->voucher($this->a));
        $b = $this->claim($this->voucher($this->b));
        $platform = $this->claim($this->voucher());
        $this->claim($this->voucher($this->a, ['min_spend' => 150]));
        $this->claim($this->voucher($this->product('c', 200)));
        $this->actingAs($this->buyer);
        foreach (['single='.$this->a->id, 'seller='.$this->a->seller_id, 'selected=1&ids='.$this->lineA->id, 'all=1'] as $query) {
            session()->forget(['checkout_single', 'checkout_seller_id', 'checkout_all', 'checkout_selected']);
            $expected = $query === 'all=1' ? [$a->id, $b->id, $platform->id] : [$a->id, $platform->id];
            $this->get('/checkout?'.$query)->assertOk()->assertViewHas('claimedVouchers', function ($claims) use ($expected) {
                $this->assertEqualsCanonicalizing($expected, $claims->pluck('id')->all());
                return true;
            });
        }
    }

    public function test_website_rejects_a_forged_foreign_store_voucher_without_mutations(): void
    {
        $claim = $this->claim($this->voucher($this->b));
        $this->actingAs($this->buyer)->from('/checkout')->post('/checkout', [
            'checkout_mode' => 'selected', 'selected_item_ids' => [$this->lineA->id], 'address_id' => $this->address->id,
            'payment_method' => 'cod', 'voucher_claim_ids' => [$claim->id],
        ])->assertRedirect('/checkout')->assertSessionHas('error', fn ($message) => str_contains($message, 'does not apply'));
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('cart_items', 2);
        $this->assertEquals(10, $this->a->fresh()->stock);
        $this->assertNull($claim->fresh()->used_at);
    }

    public function test_website_applies_a_shipping_voucher_to_its_own_store_even_when_it_is_second(): void
    {
        $claim = $this->claim($this->voucher($this->b, ['discount_type' => 'free_shipping', 'discount_value' => 0]));
        $this->actingAs($this->buyer)->post('/checkout', ['checkout_mode' => 'all', 'address_id' => $this->address->id,
            'payment_method' => 'cod', 'voucher_claim_ids' => [$claim->id]])->assertRedirect('/orders');
        $this->assertDatabaseHas('orders', ['seller_id' => $this->a->seller_id, 'shipping_fee' => 50, 'total_amount' => 150]);
        $this->assertDatabaseHas('orders', ['seller_id' => $this->b->seller_id, 'shipping_fee' => 0, 'total_amount' => 900]);
        $this->assertDatabaseCount('order_vouchers', 1);
        $this->assertNotNull($claim->fresh()->used_at);
        $this->assertSame($this->b->seller_id, (int) Order::findOrFail($claim->fresh()->order_id)->seller_id);
    }

    public function test_legacy_code_validation_uses_server_prices_and_owns_selected_cart_items(): void
    {
        $voucher = $this->voucher($this->a, ['discount_type' => 'percent', 'discount_value' => 20]);
        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/vouchers/validate', ['code' => $voucher->code, 'subtotal' => 999999])->assertOk()
            ->assertJsonPath('discount', 20)->assertJsonMissingPath('voucher.code');
        $foreign = CartItem::create(['cart_id' => Cart::headerFor($this->account('foreign')->id)->id, 'product_id' => $this->a->id, 'quantity' => 1]);
        $this->postJson('/api/vouchers/validate', ['code' => $voucher->code, 'cart_item_ids' => [$foreign->id]])->assertUnprocessable();
    }
    public function test_platform_redemption_stays_locked_until_all_discounted_store_orders_are_cancelled(): void
    {
        $claim = $this->claim($this->voucher(null, ['discount_value' => 99.99]));
        Sanctum::actingAs($this->buyer);
        $data = $this->checkoutData(['voucher_claim_id' => $claim->id]);
        $preview = $this->postJson('/api/orders/preview', $data)->assertOk()->json('quote');
        $result = $this->postJson('/api/orders/checkout', $data)->assertCreated()->assertJsonCount(2, 'orders');
        $this->assertEquals($preview['total'], collect($result->json('orders'))->sum('total_amount'));
        $orders = Order::where('buyer_id', $this->buyer->id)->orderBy('id')->get();
        $this->assertEquals(99.99, $orders->sum(fn ($order) => $order->voucherDiscountTotal()));
        \App\Support\OrderCancellation::byBuyer($orders[0], 'Changed my mind', $this->buyer->id);
        $this->assertNotNull($claim->fresh()->used_at);
        $this->assertEquals(1, $claim->voucher->fresh()->used_count);
        \App\Support\OrderCancellation::byBuyer($orders[1], 'Changed my mind', $this->buyer->id);
        $this->assertNull($claim->fresh()->used_at);
        $this->assertEquals(0, $claim->voucher->fresh()->used_count);
        $cart = Cart::headerFor($this->buyer->id);
        foreach ([$this->a, $this->b] as $product) {
            $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        }
        $this->postJson('/api/orders/checkout', $data)->assertCreated();
        \App\Support\Vouchers::restoreForOrder($orders[0]->fresh(), strict: true);
        $this->assertNotNull($claim->fresh()->used_at);
        $this->assertEquals(1, $claim->voucher->fresh()->used_count);
    }

    public function test_website_and_mobile_quote_match_for_a_platform_voucher_across_stores(): void
    {
        $claim = $this->claim($this->voucher(null, ['discount_value' => 200, 'min_spend' => 500]));
        Sanctum::actingAs($this->buyer);
        $preview = $this->postJson('/api/orders/preview', ['voucher_claim_id' => $claim->id])->assertOk()->json('quote');
        $this->actingAs($this->buyer, 'web')->post('/checkout', [
            'checkout_mode' => 'all', 'address_id' => $this->address->id, 'payment_method' => 'cod', 'voucher_claim_id' => $claim->id,
        ])->assertRedirect('/orders')->assertSessionMissing('error');
        $this->assertEquals($preview['total'], Order::where('buyer_id', $this->buyer->id)->sum('total_amount'));
        $this->assertEquals(1, $claim->voucher->fresh()->used_count);
    }

}
