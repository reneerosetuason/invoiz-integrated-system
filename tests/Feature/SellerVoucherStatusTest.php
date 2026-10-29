<?php

namespace Tests\Feature;

use App\Models\{Address, Cart, CartItem, Delivery, Order, OrderVoucher, Product, Seller, User, Voucher, VoucherClaim};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Auth, DB, Schema};
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class SellerVoucherStatusTest extends TestCase
{
    use BuyerMobileDatabase;

    private User $owner;
    private User $buyer;
    private Seller $seller;
    private Product $product;
    private Address $address;
    private CartItem $line;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::create('seller_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
        $this->owner = $this->account('owner', 'seller');
        $this->buyer = $this->account('buyer');
        $seller = Seller::create(['user_id' => $this->owner->id, 'store_name' => 'Voucher Fixture Store', 'status' => 'active',
            'approval_status' => 'approved', 'vacation_mode' => false, 'address_line' => '1 Test Street',
            'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila']);
        DB::table('sellers')->where('id', $seller->id)->update(['id' => 1000 + $this->owner->id]);
        $this->seller = Seller::where('user_id', $this->owner->id)->firstOrFail();
        $this->product = Product::create(['seller_id' => $this->owner->id, 'name' => 'Voucher fixture product', 'price' => 100, 'stock' => 10, 'status' => 'active']);
        $this->line = CartItem::create(['cart_id' => Cart::headerFor($this->buyer->id)->id, 'product_id' => $this->product->id, 'quantity' => 1]);
        $this->address = Address::create(['buyer_id' => $this->buyer->id, 'recipient_name' => 'Fixture Buyer', 'phone' => '09171234567',
            'address_line' => '2 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_default' => true]);
    }

    private function account(string $name, string $role = 'buyer'): User
    {
        return User::create(['name' => $name, 'email' => $name.'@example.test', 'password' => 'Password123!', 'role' => $role,
            'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function voucher(array $attributes = []): Voucher
    {
        return Voucher::create($attributes + ['seller_id' => $this->seller->id, 'code' => 'STATUS'.(Voucher::count() + 1),
            'name' => 'Fixture voucher', 'discount_type' => 'fixed', 'discount_value' => 10, 'min_spend' => 0, 'status' => 'active', 'used_count' => 0]);
    }

    private function claim(Voucher $voucher): VoucherClaim
    {
        return VoucherClaim::create(['voucher_id' => $voucher->id, 'buyer_id' => $this->buyer->id, 'claimed_at' => now()]);
    }

    private function sellerSession(): self
    {
        Auth::forgetGuards();
        return $this->actingAs($this->owner)->withSession(['active_role' => 'seller']);
    }

    private function buyerSession(): self
    {
        Auth::forgetGuards();
        return $this->actingAs($this->buyer)->withSession(['active_role' => 'buyer']);
    }

    private function changeStatus(Voucher $voucher, string $status)
    {
        return $this->sellerSession()->from('/seller/vouchers')
            ->post('/seller/vouchers/'.$voucher->id.'/status', ['status' => $status]);
    }

    public function test_seller_can_deactivate_and_reactivate_an_unclaimed_voucher_with_only_its_status(): void
    {
        $voucher = $this->voucher();
        $original = $voucher->getAttributes();
        foreach (['inactive', 'active'] as $status) {
            $this->changeStatus($voucher, $status)->assertRedirect('/seller/vouchers')->assertSessionHas('success')->assertSessionHasNoErrors();
            $this->assertSame($status, $voucher->fresh()->status);
            foreach (['code', 'name', 'discount_type', 'discount_value', 'min_spend', 'used_count', 'seller_id'] as $field) {
                $this->assertEquals($original[$field], $voucher->fresh()->getRawOriginal($field));
            }
        }
    }

    public function test_claimed_vouchers_can_be_deactivated_and_edit_cannot_change_their_terms(): void
    {
        $voucher = $this->voucher();
        $claim = $this->claim($voucher);
        $this->changeStatus($voucher, 'inactive')->assertRedirect('/seller/vouchers');
        $this->sellerSession()->from('/seller/vouchers')->post('/seller/vouchers/'.$voucher->id, [
            'status' => 'active', 'name' => 'Changed', 'discount_type' => 'percent', 'discount_value' => 90,
        ])->assertRedirect('/seller/vouchers')->assertSessionHas('success');
        $this->assertSame('active', $voucher->fresh()->status);
        $this->assertSame('Fixture voucher', $voucher->fresh()->name);
        $this->assertEquals(10, $voucher->fresh()->discount_value);
        $this->assertNull($claim->fresh()->used_at);
        $this->assertDatabaseCount('voucher_claims', 1);
        $this->sellerSession()->delete('/seller/vouchers/'.$voucher->id)->assertSessionHas('error');
        $this->assertModelExists($voucher);
    }

    public function test_seller_management_lists_only_owned_vouchers_and_rejects_unassigned_records(): void
    {
        $own = $this->voucher();
        $platform = $this->voucher(['seller_id' => null, 'name' => 'Unassigned voucher']);
        $response = $this->sellerSession()->get('/seller/vouchers')->assertOk();
        $response->assertDontSee('Unassigned voucher')->assertDontSee('Managed by the platform');
        $response->assertSee(route('seller.vouchers.status', $own->id), false)
            ->assertDontSee(route('seller.vouchers.status', $platform->id), false)
            ->assertDontSee('openEdit('.$platform->id.')', false);
        $response->assertViewHas('voucherMap', fn ($map) => $map->pluck('id')->all() === [$own->id]);
        $this->changeStatus($platform, 'inactive')->assertNotFound();
        $this->sellerSession()->post('/seller/vouchers/'.$platform->id, ['status' => 'inactive'])->assertNotFound();
        $this->sellerSession()->delete('/seller/vouchers/'.$platform->id)->assertNotFound();
        $this->assertSame('active', $platform->fresh()->status);
    }

    public function test_created_voucher_is_linked_to_the_current_store_and_can_be_managed(): void
    {
        $data = ['code' => ' myoffer ', 'name' => 'My store offer', 'discount_type' => 'fixed', 'discount_value' => 25,
            'min_spend' => 50, 'status' => 'active', 'seller_id' => null];
        $this->sellerSession()->from('/seller/vouchers')->post('/seller/vouchers', $data)
            ->assertRedirect('/seller/vouchers')->assertSessionHasNoErrors()->assertSessionHas('success');
        $voucher = Voucher::where('code', 'MYOFFER')->firstOrFail();
        $this->assertSame($this->seller->id, (int) $voucher->seller_id);
        $this->sellerSession()->get('/seller/vouchers')->assertOk()->assertSee('My store offer')
            ->assertSee(route('seller.vouchers.status', $voucher->id), false)->assertSee('openEdit('.$voucher->id.')', false);
        $this->sellerSession()->from('/seller/vouchers')->post('/seller/vouchers/'.$voucher->id,
            ['name' => 'Edited store offer', 'discount_type' => 'fixed', 'discount_value' => 30, 'min_spend' => 50, 'status' => 'active'])
            ->assertRedirect('/seller/vouchers')->assertSessionHasNoErrors();
        $this->assertSame('Edited store offer', $voucher->fresh()->name);
        $this->assertEquals(30, $voucher->fresh()->discount_value);
        $this->changeStatus($voucher, 'inactive')->assertRedirect('/seller/vouchers');
        $this->assertSame('inactive', $voucher->fresh()->status);
        $this->changeStatus($voucher, 'active')->assertRedirect('/seller/vouchers');
        $this->buyerSession()->get('/vouchers')->assertOk()->assertViewHas('vouchers', fn ($items) => $items->contains('id', $voucher->id));
    }

    public function test_ownership_repair_preserves_past_platform_discounts_and_restricts_future_use_to_the_store(): void
    {
        $voucher = $this->voucher(['seller_id' => null]);
        $shipping = $this->voucher(['seller_id' => null, 'discount_type' => 'free_shipping', 'discount_value' => 50]);
        $otherOwner = $this->account('historical-store', 'seller');
        Seller::create(['user_id' => $otherOwner->id, 'store_name' => 'Historical Store', 'status' => 'active', 'approval_status' => 'approved',
            'vacation_mode' => false, 'address_line' => '1 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila']);
        $otherProduct = Product::create(['seller_id' => $otherOwner->id, 'name' => 'Other product', 'price' => 100, 'stock' => 10, 'status' => 'active']);
        CartItem::create(['cart_id' => $this->line->cart_id, 'product_id' => $otherProduct->id, 'quantity' => 1]);
        $claim = $this->claim($voucher);
        $shipClaim = $this->claim($shipping);
        Sanctum::actingAs($this->buyer);
        $id = $this->postJson('/api/orders/checkout', ['voucher_claim_ids' => [$claim->id, $shipClaim->id], 'address_id' => $this->address->id,
            'payment_method' => 'cash_on_delivery'])->assertCreated()->json('order.id');
        $historical = Order::findOrFail($id);
        $before = [];
        foreach ($historical->shippingGroupIds() as $sid) {
            $before[$sid] = [$historical->voucherDiscountForSeller($sid), $historical->shippingWaivedForSeller($sid), $historical->cashToCollectForSeller($sid)];
        }
        $migration = require database_path('migrations/2026_10_29_000002_snapshot_order_voucher_store_scope.php');
        $migration->up();
        $this->assertDatabaseHas('order_vouchers', ['order_id' => $id, 'voucher_id' => $voucher->id, 'seller_user_id' => 0]);
        $voucher->update(['seller_id' => $this->seller->id]);
        $shipping->update(['seller_id' => $this->seller->id]);
        $historical = $historical->fresh();
        foreach ($before as $sid => $amounts) {
            $this->assertEquals($amounts, [$historical->voucherDiscountForSeller($sid), $historical->shippingWaivedForSeller($sid), $historical->cashToCollectForSeller($sid)]);
        }
        $migration->up();
        $this->assertDatabaseHas('order_vouchers', ['order_id' => $id, 'voucher_id' => $voucher->id, 'seller_user_id' => 0]);
        $this->sellerSession()->get('/seller/vouchers')->assertOk()->assertSee(route('seller.vouchers.status', $voucher->id), false);
        $this->changeStatus($voucher, 'inactive')->assertRedirect('/seller/vouchers');
        $this->changeStatus($voucher, 'active')->assertRedirect('/seller/vouchers');
        $this->assertSame($this->seller->id, (int) $voucher->fresh()->seller_id);
        // Another buyer's new checkout sees store scope, not the historical
        // platform-wide interpretation frozen on the old order.
        $newBuyer = $this->account('new-voucher-buyer');
        $otherLine = CartItem::create(['cart_id' => Cart::headerFor($newBuyer->id)->id, 'product_id' => $otherProduct->id, 'quantity' => 1]);
        Auth::forgetGuards();
        Sanctum::actingAs($newBuyer);
        $this->postJson('/api/orders/preview', ['cart_item_ids' => [$otherLine->id]])->assertOk()->assertJsonCount(0, 'vouchers');
        $newClaim = VoucherClaim::create(['voucher_id' => $voucher->id, 'buyer_id' => $newBuyer->id, 'claimed_at' => now()]);
        $this->postJson('/api/orders/preview', ['voucher_claim_id' => $newClaim->id])->assertUnprocessable();
    }

    public function test_new_orders_snapshot_store_scope_and_historical_redemptions_lock_offer_terms(): void
    {
        (require database_path('migrations/2026_10_29_000002_snapshot_order_voucher_store_scope.php'))->up();
        $voucher = $this->voucher();
        $order = Order::create(['buyer_id' => $this->buyer->id, 'seller_id' => $this->owner->id, 'status' => 'pending', 'total_amount' => 140]);
        $applied = OrderVoucher::create(['order_id' => $order->id, 'voucher_id' => $voucher->id, 'discount_amount' => 10]);
        $this->assertSame($this->owner->id, (int) $applied->fresh()->seller_user_id);
        $this->sellerSession()->from('/seller/vouchers')->post('/seller/vouchers/'.$voucher->id,
            ['name' => 'Changed', 'discount_value' => 99, 'discount_type' => 'free_shipping', 'status' => 'inactive'])->assertRedirect('/seller/vouchers');
        $this->assertSame('Fixture voucher', $voucher->fresh()->name);
        $this->assertEquals(10, $voucher->fresh()->discount_value);
        $this->assertSame('inactive', $voucher->fresh()->status);
        $this->sellerSession()->delete('/seller/vouchers/'.$voucher->id)->assertSessionHas('error');
        $this->assertModelExists($voucher);
    }

    public function test_seller_edit_configuration_uses_the_registered_route_and_marks_claimed_terms_locked(): void
    {
        $claimed = $this->voucher(['name' => 'Claimed store voucher']);
        $this->claim($claimed);
        $plain = $this->voucher(['name' => 'New store voucher', 'status' => 'inactive']);
        $this->voucher(['seller_id' => null, 'name' => 'Platform voucher']);
        $response = $this->sellerSession()->get('/seller/vouchers')->assertOk();
        $html = $response->getContent();
        preg_match('/data-config="([^"]+)"/', substr($html, strpos($html, '/js/vouchers/index.js')), $configMatch);
        $config = json_decode(html_entity_decode($configMatch[1], ENT_QUOTES | ENT_HTML5), true, flags: JSON_THROW_ON_ERROR)[0];
        $this->assertSame(route('seller.vouchers.update', $claimed->id), $config[$claimed->id]['update_url']);
        $this->assertTrue($config[$claimed->id]['has_claims']);
        $this->assertFalse($config[$plain->id]['has_claims']);
        $this->assertSame('inactive', $config[$plain->id]['status']);
        if ($renderDir = getenv('INVOIZ_VOUCHER_RENDER_DIR')) {
            if (! is_dir($renderDir)) mkdir($renderDir, 0777, true);
            file_put_contents($renderDir.'/seller-vouchers.html', $html);
        }
    }

    public function test_foreign_or_missing_vouchers_cannot_be_changed_and_status_is_validated(): void
    {
        $own = $this->voucher();
        $otherOwner = $this->account('other-owner', 'seller');
        $other = Seller::create(['user_id' => $otherOwner->id, 'store_name' => 'Other Store', 'status' => 'active', 'approval_status' => 'approved']);
        $foreign = $this->voucher(['seller_id' => $other->id]);
        $this->changeStatus($foreign, 'inactive')->assertNotFound();
        $this->sellerSession()->post('/seller/vouchers/999999/status', ['status' => 'inactive'])->assertNotFound();
        foreach (['', 'disabled', '0'] as $invalid) {
            $this->changeStatus($own, $invalid)->assertSessionHasErrors('status');
        }
        $this->assertSame('active', $own->fresh()->status);
        $this->assertSame('active', $foreign->fresh()->status);
        $this->buyerSession()->post('/seller/vouchers/'.$own->id.'/status', ['status' => 'inactive'])->assertRedirect('/shop');
    }

    public function test_legacy_user_keys_work_without_crossing_colliding_seller_record_ids(): void
    {
        $legacy = $this->voucher(['seller_id' => $this->owner->id]);
        $this->changeStatus($legacy, 'inactive')->assertRedirect('/seller/vouchers');
        $otherOwner = $this->account('collision-owner', 'seller');
        $collision = Seller::create(['user_id' => $otherOwner->id, 'store_name' => 'Collision Store', 'status' => 'active', 'approval_status' => 'approved']);
        DB::table('sellers')->where('id', $collision->id)->update(['id' => $this->owner->id]);
        // The same key now belongs to another seller record; user-ID fallback
        // must stop granting the original seller access to it.
        $this->changeStatus($legacy, 'active')->assertNotFound();
        $this->assertSame('inactive', $legacy->fresh()->status);
    }

    public function test_boolean_status_schema_renders_and_saves_the_same_active_inactive_controls(): void
    {
        Schema::table('vouchers', fn (Blueprint $table) => $table->boolean('status')->default(true)->change());
        $voucher = $this->voucher();
        $this->sellerSession()->get('/seller/vouchers')->assertOk()->assertSee('Active')->assertSee('Deactivate');
        $this->changeStatus($voucher, 'inactive')->assertRedirect('/seller/vouchers');
        $this->assertFalse($voucher->fresh()->status_bool);
        $this->sellerSession()->get('/seller/vouchers')->assertOk()->assertSee('Inactive')->assertSee('Activate');
        $this->changeStatus($voucher, 'active')->assertRedirect('/seller/vouchers');
        $this->assertTrue($voucher->fresh()->status_bool);
    }

    public function test_deactivation_removes_the_voucher_from_web_and_api_and_blocks_stale_checkout(): void
    {
        $voucher = $this->voucher();
        $claim = $this->claim($voucher);
        $this->buyerSession()->get('/vouchers')->assertOk()->assertViewHas('vouchers', fn ($items) => $items->contains('id', $voucher->id));
        $this->changeStatus($voucher, 'inactive')->assertRedirect('/seller/vouchers');
        $this->buyerSession()->get('/vouchers')->assertOk()->assertViewHas('vouchers', fn ($items) => $items->isEmpty());
        $this->get('/checkout?seller='.$this->owner->id)->assertOk()->assertViewHas('claimedVouchers', fn ($items) => $items->isEmpty());
        $this->from('/vouchers')->post('/vouchers/'.$voucher->id.'/claim')->assertSessionHas('error');
        $this->from('/checkout')->post('/checkout', ['checkout_mode' => 'selected', 'selected_item_ids' => [$this->line->id],
            'address_id' => $this->address->id, 'payment_method' => 'cod', 'voucher_claim_ids' => [$claim->id]])->assertSessionHas('error');
        Sanctum::actingAs($this->buyer);
        $this->getJson('/api/vouchers')->assertOk()->assertJsonCount(0, 'vouchers');
        $this->getJson('/api/vouchers/claimed')->assertOk()->assertJsonCount(0, 'vouchers');
        $this->postJson('/api/vouchers/'.$voucher->id.'/claim')->assertUnprocessable();
        $this->postJson('/api/vouchers/validate', ['code' => $voucher->code])->assertUnprocessable();
        $this->postJson('/api/orders/preview')->assertOk()->assertJsonCount(0, 'vouchers');
        $this->postJson('/api/orders/preview', ['voucher_claim_id' => $claim->id])->assertUnprocessable();
        $this->postJson('/api/orders/checkout', ['voucher_claim_id' => $claim->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash_on_delivery'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertEquals(10, $this->product->fresh()->stock);
        $this->assertNull($claim->fresh()->used_at);
        $this->changeStatus($voucher, 'active')->assertRedirect('/seller/vouchers');
        $this->buyerSession()->get('/vouchers')->assertOk()->assertViewHas('vouchers', fn ($items) => $items->contains('id', $voucher->id));
        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/orders/preview', ['voucher_claim_id' => $claim->id])->assertOk()->assertJsonPath('quote.discount', 10);
        $this->assertDatabaseCount('voucher_claims', 1);
    }

    public function test_deactivation_preserves_existing_order_discounts_and_cancellation_does_not_reactivate_the_voucher(): void
    {
        $voucher = $this->voucher();
        $claim = $this->claim($voucher);
        Sanctum::actingAs($this->buyer);
        $id = $this->postJson('/api/orders/checkout', ['voucher_claim_id' => $claim->id, 'address_id' => $this->address->id,
            'payment_method' => 'cash_on_delivery'])->assertCreated()->json('order.id');
        $before = Order::findOrFail($id)->getAttributes();
        $delivery = Delivery::where('order_id', $id)->firstOrFail()->getAttributes();
        $this->changeStatus($voucher, 'inactive')->assertRedirect('/seller/vouchers');
        $this->assertEquals($before, Order::findOrFail($id)->getAttributes());
        $this->assertEquals($delivery, Delivery::where('order_id', $id)->firstOrFail()->getAttributes());
        $this->assertEquals(10, Order::findOrFail($id)->voucherDiscountForSeller($this->owner->id));
        $this->assertNotNull($claim->fresh()->used_at);
        Sanctum::actingAs($this->buyer);
        $this->postJson('/api/orders/'.$id.'/cancel', ['reason' => 'changed_mind'])->assertOk();
        $this->assertSame('inactive', $voucher->fresh()->status);
        $this->assertEquals(0, $voucher->fresh()->used_count);
        $this->assertNull($claim->fresh()->used_at);
        $this->getJson('/api/vouchers')->assertOk()->assertJsonCount(0, 'vouchers');
    }
}
