<?php

namespace Tests\Feature;

use App\Mail\OtpCodeMail;
use App\Mail\RegistrationDecision;
use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Conversation;
use App\Models\Delivery;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\ProductSale;
use App\Models\Review;
use App\Models\Sale;
use App\Models\Seller;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class BuyerMobileIntegrationTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
    }

    private function buyer(string $email = 'mobile@example.test'): User
    {
        return User::create(['name' => 'Mobile Buyer', 'first_name' => 'Mobile', 'last_name' => 'Buyer', 'email' => $email,
            'password' => 'Password123!', 'role' => 'buyer', 'status' => 'active', 'account_status' => 'active',
            'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function product(string $email = 'seller@example.test'): Product
    {
        $user = $this->buyer($email);
        $user->update(['role' => 'seller']);
        $seller = Seller::create(['user_id' => $user->id, 'store_name' => 'Mobile Store', 'status' => 'active', 'approval_status' => 'approved',
            'vacation_mode' => false, 'address_line' => '1 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila']);
        // Keep the two legacy ID namespaces disjoint in the shared-record fixture.
        DB::table('sellers')->where('id', $seller->id)->update(['id' => 1000 + $user->id]);

        return Product::create(['seller_id' => $user->id, 'name' => 'Mobile product', 'price' => 100, 'stock' => 10, 'status' => 'active', 'image' => 'products/test.png']);
    }

    private function address(User $buyer): Address
    {
        return Address::create(['buyer_id' => $buyer->id, 'recipient_name' => 'Mobile Buyer', 'phone' => '09171234567',
            'address_line' => '2 Test Street', 'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_default' => true]);
    }

    private function line(User $buyer, Product $product, int $quantity = 1): CartItem
    {
        return CartItem::create(['cart_id' => Cart::headerFor($buyer->id)->id, 'product_id' => $product->id, 'quantity' => $quantity]);
    }

    public function test_every_imported_mobile_endpoint_is_registered_and_implemented(): void
    {
        $contract = json_decode(file_get_contents(base_path('buyer_mobile/api-contract.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertCount(18, $contract['screens']);
        foreach ($contract['endpoints'] as $endpoint) {
            $path = preg_replace('/\{[^}]+\}/', '1', $endpoint['path']);
            $route = app('router')->getRoutes()->match(Request::create($path, $endpoint['method']));
            $action = $route->getActionName();
            if ($action !== 'Closure') {
                [$controller,$method] = explode('@', $action);
                $this->assertTrue(method_exists($controller, $method), $endpoint['method'].' '.$path);
            }
        }
    }

    public function test_mobile_registration_verification_and_web_login_share_one_account(): void
    {
        $data = ['first_name' => 'Mobile', 'last_name' => 'Customer', 'sex' => 'other', 'email' => 'new-mobile@example.test',
            'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'phone' => '09171234567', 'birthday' => '2000-01-01'];
        $this->postJson('/api/mobile/buyer/register', $data)->assertCreated()->assertJsonPath('verification_required', true)->assertJsonMissingPath('token');
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertNull($user->email_verified_at);
        Mail::assertSent(OtpCodeMail::class);
        $this->postJson('/api/mobile/buyer/login', $data)->assertForbidden()->assertJsonPath('verification_required', true);
        $this->postJson('/api/mobile/buyer/verify-email', ['email' => $user->email, 'code' => $user->otp])->assertOk();
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertNull($user->fresh()->otp);
        $this->assertSame('pending', $user->fresh()->approval_status);
        $this->postJson('/api/mobile/buyer/login', $data)->assertForbidden();
        $admin = $this->buyer('admin@example.test');
        $admin->update(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin)->post('/admin/buyers/'.$user->id.'/review', ['action' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $user->fresh()->approval_status);
        Mail::assertSent(RegistrationDecision::class);
        $this->postJson('/api/mobile/buyer/login', $data)->assertOk()->assertJsonPath('user.id', $user->id)->assertJsonStructure(['token']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Password123!'])->assertRedirect('/shop');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_website_otp_can_be_consumed_once_by_the_mobile_verifier(): void
    {
        $user = $this->buyer();
        $user->update(['email_verified_at' => null, 'otp' => '123456', 'otp_expires_at' => now()->addMinutes(10)]);
        $this->postJson('/api/mobile/buyer/verify-email', ['email' => $user->email, 'code' => '123456'])->assertOk();
        $this->postJson('/api/mobile/buyer/verify-email', ['email' => $user->email, 'code' => '123456'])->assertUnprocessable();
    }

    public function test_buyer_review_is_admin_only_and_requires_email_verification(): void
    {
        $user = $this->buyer();
        $user->update(['approval_status' => 'pending', 'email_verified_at' => null]);
        $this->actingAs($user)->postJson('/admin/buyers/'.$user->id.'/review', ['action' => 'approve'])->assertForbidden();
        $admin = $this->buyer('admin@example.test');
        $admin->update(['role' => 'admin', 'is_admin' => true]);
        $this->actingAs($admin)->postJson('/admin/buyers/'.$user->id.'/review', ['action' => 'approve'])->assertUnprocessable();
        $this->get('/admin/buyers')->assertOk()->assertSee('Pending approval')->assertSee('Approve');
        $this->postJson('/admin/buyers/'.$user->id.'/review', ['action' => 'reject', 'reason' => 'Registration declined'])->assertOk()->assertJsonPath('approval_status', 'rejected');
        $this->postJson('/admin/buyers/'.$user->id.'/review', ['action' => 'approve'])->assertStatus(409);
        $this->assertSame('rejected', $user->fresh()->approval_status);
    }

    public function test_mobile_verification_enforces_expiry_attempt_limits_and_resend_cooldown(): void
    {
        $user = $this->buyer();
        $user->update(['email_verified_at' => null, 'otp' => '123456', 'otp_expires_at' => now()->subMinute()]);
        $this->postJson('/api/mobile/buyer/verify-email', ['email' => $user->email, 'code' => '123456'])->assertUnprocessable();
        $user->update(['otp_expires_at' => now()->addMinutes(10)]);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/mobile/buyer/verify-email', ['email' => $user->email, 'code' => '654321'])->assertUnprocessable();
        }
        $this->postJson('/api/mobile/buyer/verify-email', ['email' => $user->email, 'code' => '123456'])->assertStatus(429);
        cache()->put('otp_resend_'.$user->id, true, 60);
        $this->postJson('/api/mobile/buyer/resend-code', ['email' => $user->email])->assertStatus(429);
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_existing_web_account_can_use_the_mobile_token_and_suspension_is_shared(): void
    {
        $user = $this->buyer();
        $token = $this->postJson('/api/mobile/buyer/login', ['email' => $user->email, 'password' => 'Password123!'])->assertOk()->json('token');
        $this->withToken($token)->getJson('/api/me')->assertOk()->assertJsonPath('user.id', $user->id);
        $user->update(['account_status' => 'suspended']);
        Auth::forgetGuards();
        $this->getJson('/api/cart')->assertForbidden();
    }

    public function test_profile_uploads_and_fields_update_the_shared_account_without_role_escalation(): void
    {
        Storage::fake('public');
        $user = $this->buyer();
        Sanctum::actingAs($user);
        $this->postJson('/api/profile', ['first_name' => 'Updated', 'bio' => 'Shared profile', 'role' => 'admin', 'is_admin' => true,
            'profile_picture' => UploadedFile::fake()->createWithContent('avatar.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII='))])->assertOk()->assertJsonPath('user.bio', 'Shared profile');
        $this->assertSame('buyer', $user->fresh()->role);
        $this->assertSame('Updated', $user->fresh()->first_name);
        Storage::disk('public')->assertExists($user->fresh()->profile_picture);
        $this->getJson('/api/me')->assertJsonPath('user.profile_picture', $user->fresh()->profile_picture);
    }

    public function test_mobile_address_book_and_foreign_address_ownership(): void
    {
        $user = $this->buyer();
        $foreign = $this->address($this->buyer('other@example.test'));
        Sanctum::actingAs($user);
        $saved = $this->postJson('/api/addresses', ['recipient_name' => 'Mobile Buyer', 'phone' => '09171234567', 'address_line' => '1 Test Street',
            'barangay' => 'Test Barangay', 'city' => 'Manila', 'province' => 'Metro Manila'])->assertCreated()->json('address.id');
        $this->getJson('/api/addresses')->assertJsonCount(1, 'addresses');
        $this->putJson('/api/addresses/'.$saved, ['recipient_name' => 'Updated Recipient'])->assertOk();
        $this->putJson('/api/addresses/'.$foreign->id, ['recipient_name' => 'Wrong Owner'])->assertNotFound();
    }

    public function test_mobile_cart_put_is_visible_in_the_website_cart(): void
    {
        $user = $this->buyer();
        $product = $this->product();
        Sanctum::actingAs($user);
        $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $id = CartItem::firstOrFail()->id;
        $this->putJson('/api/cart/'.$id, ['quantity' => 3])->assertOk();
        $this->assertSame(3, Cart::itemsFor($user->id)->first()->quantity);
        $this->actingAs($user)->get('/cart')->assertOk()->assertSee('Mobile product');
    }

    public function test_selected_checkout_preview_equals_order_and_preserves_unselected_cart_items(): void
    {
        $user = $this->buyer();
        $product = $this->product();
        $other = $this->product('second-seller@example.test');
        $line = $this->line($user, $product, 2);
        $unselected = $this->line($user, $other);
        $address = $this->address($user);
        Sanctum::actingAs($user);
        $selection = ['cart_item_ids' => [$line->id]];
        $quote = $this->postJson('/api/orders/preview', $selection)->assertOk()->json('quote');
        $this->assertEquals(200, $quote['subtotal']);
        $this->assertEquals(Order::SHIPPING_FEE_PER_SELLER, $quote['shipping_fee']);
        $response = $this->postJson('/api/orders/checkout', $selection + ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery'])->assertCreated();
        $this->assertEquals($quote['total'], $response->json('order.total_amount'));
        $id = $response->json('order.id');
        $this->assertSame($id, Delivery::firstOrFail()->order_id);
        $this->assertDatabaseHas('order_items', ['order_id' => $id, 'seller_id' => $product->seller_id]);
        $this->assertDatabaseHas('cart_items', ['id' => $unselected->id]);
        $this->assertDatabaseMissing('cart_items', ['id' => $line->id]);
        $this->getJson('/api/orders/'.$id)->assertOk()->assertJsonPath('order.id', $id);
        $this->assertSame(8, $product->fresh()->stock);
    }

    public function test_mobile_voucher_claim_applies_to_the_real_order_and_hides_internal_code(): void
    {
        $user = $this->buyer();
        $product = $this->product();
        $this->line($user, $product);
        $address = $this->address($user);
        $voucher = Voucher::create(['code' => 'INTERNAL', 'name' => 'Mobile offer', 'discount_type' => 'fixed', 'discount_value' => 10, 'min_spend' => 0, 'status' => 'active', 'used_count' => 0]);
        Sanctum::actingAs($user);
        $this->getJson('/api/vouchers')->assertOk()->assertJsonMissingPath('vouchers.0.code');
        $claim = $this->postJson('/api/vouchers/'.$voucher->id.'/claim')->assertCreated()->json('claim.id');
        $quote = $this->postJson('/api/orders/preview', ['voucher_claim_id' => $claim])->assertOk()->json('quote');
        $this->assertEquals(10, $quote['discount']);
        $response = $this->postJson('/api/orders/checkout', ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery', 'voucher_claim_id' => $claim])->assertCreated();
        $this->assertEquals($quote['total'], $response->json('order.total_amount'));
        $this->assertNotNull(VoucherClaim::findOrFail($claim)->used_at);
    }

    public function test_foreign_selected_cart_items_and_voucher_claims_are_rejected(): void
    {
        $user = $this->buyer();
        $other = $this->buyer('other@example.test');
        $product = $this->product();
        $this->line($user, $product);
        $foreign = $this->line($other, $product);
        $voucher = Voucher::create(['code' => 'OFFER', 'discount_type' => 'fixed', 'discount_value' => 10, 'status' => 'active']);
        $claim = VoucherClaim::create(['buyer_id' => $other->id, 'voucher_id' => $voucher->id, 'claimed_at' => now()]);
        Sanctum::actingAs($user);
        $this->postJson('/api/orders/preview', ['cart_item_ids' => [$foreign->id]])->assertUnprocessable();
        $this->postJson('/api/orders/preview', ['voucher_claim_id' => $claim->id])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_mobile_store_follow_and_favorites_are_visible_with_a_bearer_token(): void
    {
        $user = $this->buyer();
        $product = $this->product();
        $token = $user->createToken('mobile-test')->plainTextToken;
        $this->withToken($token)->postJson('/api/favorites/toggle', ['product_id' => $product->id])->assertOk();
        $this->getJson('/api/products/'.$product->id)->assertOk()->assertJsonPath('product.is_favorited', true);
        $this->postJson('/api/stores/'.$product->seller_id.'/follow')->assertOk()->assertJsonPath('is_following', true);
        $this->getJson('/api/stores/'.$product->seller_id)->assertOk()->assertJsonPath('store.is_following', true);
        $this->deleteJson('/api/stores/'.$product->seller_id.'/follow')->assertOk()->assertJsonPath('is_following', false);
        $this->deleteJson('/api/stores/'.$product->seller_id.'/follow')->assertOk()->assertJsonPath('is_following', false);
    }

    public function test_mobile_conversations_unread_badges_and_foreign_thread_isolation(): void
    {
        $user = $this->buyer();
        $product = $this->product();
        $other = $this->buyer('other@example.test');
        $foreign = Conversation::create(['buyer_id' => $other->id, 'seller_id' => $product->seller_id, 'subject' => 'Other buyer']);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/conversations', ['seller_id' => $product->seller_id, 'subject' => 'Product question', 'body' => 'Is this available?'])->assertCreated()->json('conversation.id');
        Message::create(['conversation_id' => $id, 'sender_id' => $product->seller_id, 'receiver_id' => $user->id, 'body' => 'Yes', 'is_read' => false]);
        $this->getJson('/api/conversations/unread-count')->assertOk()->assertJsonPath('unread_count', 1);
        $this->getJson('/api/conversations/'.$id.'/messages')->assertOk()->assertJsonCount(2, 'messages');
        $this->getJson('/api/conversations/unread-count')->assertJsonPath('unread_count', 0);
        $this->postJson('/api/conversations/'.$id.'/reply', ['body' => 'Thank you'])->assertCreated();
        $this->getJson('/api/conversations/'.$foreign->id.'/messages')->assertNotFound();
        $this->postJson('/api/conversations/'.$foreign->id.'/reply', ['body' => 'Wrong buyer'])->assertNotFound();
    }

    public function test_mobile_notification_read_paths_are_scoped_to_the_buyer(): void
    {
        $user = $this->buyer();
        $order = Order::create(['buyer_id' => $user->id, 'status' => 'pending', 'total_amount' => 100]);
        $history = OrderStatusHistory::create(['order_id' => $order->id, 'to_status' => 'pending', 'note' => 'Order placed']);
        Sanctum::actingAs($user);
        $this->getJson('/api/notifications')->assertOk()->assertJsonPath('unread_count', 1);
        $this->postJson('/api/notifications/order-'.$history->id.'/read')->assertOk();
        $this->getJson('/api/notifications')->assertJsonPath('unread_count', 0);
        $this->postJson('/api/notifications/order-99999/read')->assertNotFound();
        $this->postJson('/api/notifications/read-all')->assertOk();
    }

    public function test_seller_status_uses_the_existing_store_and_application_rules(): void
    {
        $user = $this->buyer();
        Sanctum::actingAs($user);
        $this->getJson('/api/seller/status')->assertOk()->assertJsonPath('seller', null);
        $this->postJson('/api/seller/apply', [])->assertUnprocessable()->assertJsonValidationErrors(['business_name', 'tin']);
        Seller::create(['user_id' => $user->id, 'store_name' => 'Shared store', 'status' => 'active', 'approval_status' => 'approved']);
        $this->getJson('/api/seller/me')->assertOk()->assertJsonPath('seller.user_id', $user->id);
    }

    public function test_mobile_order_cancel_and_rating_keep_existing_shared_rules(): void
    {
        $user = $this->buyer();
        $product = $this->product();
        $this->line($user, $product);
        $address = $this->address($user);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/orders/checkout', ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery'])->assertCreated()->json('order.id');
        $this->postJson('/api/orders/'.$id.'/rate', ['product_id' => $product->id, 'rating' => 5])->assertUnprocessable();
        $this->postJson('/api/orders/'.$id.'/cancel', ['reason' => 'changed_mind'])->assertOk();
        $this->assertSame('cancelled', Order::findOrFail($id)->status);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_guest_mobile_mutations_require_authentication(): void
    {
        foreach (['profile', 'conversations', 'cart/add', 'orders/checkout', 'seller/apply'] as $path) {
            $this->postJson('/api/'.$path, [])->assertUnauthorized();
        }
    }

    public function test_public_mobile_catalog_does_not_expose_reviewer_private_fields(): void
    {
        $user = $this->buyer();
        $product = $this->product();
        $user->update(['otp' => '123456', 'otp_expires_at' => now()->addMinutes(10), 'id_image' => 'ids/private.png']);
        Review::create(['buyer_id' => $user->id, 'product_id' => $product->id, 'rating' => 5, 'status' => 'visible']);
        $this->getJson('/api/products')->assertOk()->assertJsonPath('data.0.id', $product->id);
        $this->getJson('/api/categories')->assertOk()->assertJsonStructure(['categories']);
        $this->getJson('/api/products/'.$product->id)->assertOk()
            ->assertJsonPath('product.reviews.0.buyer.first_name', 'Mobile')
            ->assertJsonMissingPath('product.reviews.0.buyer.email')
            ->assertJsonMissingPath('product.reviews.0.buyer.otp')
            ->assertJsonMissingPath('product.reviews.0.buyer.id_image');
    }

    public function test_mobile_sale_filter_discount_sort_and_gallery_use_shared_catalog_data(): void
    {
        $discounted = $this->product();
        $discounted->update(['compare_at_price' => 200]);
        $normal = $this->product('normal@example.test');
        $saleProduct = $this->product('sale@example.test');
        $sale = Sale::create(['seller_id' => $saleProduct->seller_id, 'name' => 'Shared sale', 'discount_type' => 'percent', 'discount_value' => 20, 'status' => 'active', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        ProductSale::create(['sale_id' => $sale->id, 'product_id' => $saleProduct->id]);
        $response = $this->getJson('/api/products?on_sale=1&sort=discount')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $discounted->id);
        $this->assertEquals(80, collect($response->json('data'))->firstWhere('id', $saleProduct->id)['effective_price']);
        $this->assertNotContains($normal->id, collect($response->json('data'))->pluck('id')->all());
        $this->getJson('/api/products?per_page=-1')->assertUnprocessable();
    }

    public function test_mobile_shipping_quote_counts_each_store_and_free_shipping_is_applied(): void
    {
        $user = $this->buyer();
        $a = $this->product();
        $b = $this->product('second@example.test');
        $this->line($user, $a);
        $this->line($user, $b);
        $voucher = Voucher::create(['code' => 'SHIP', 'name' => 'Shipping offer', 'discount_type' => 'free_shipping', 'discount_value' => Order::SHIPPING_FEE_PER_SELLER, 'status' => 'active', 'used_count' => 0]);
        $address = $this->address($user);
        Sanctum::actingAs($user);
        $base = $this->postJson('/api/orders/preview')->assertOk()->json('quote');
        $this->assertEquals(2 * Order::SHIPPING_FEE_PER_SELLER, $base['shipping_fee']);
        $id = $this->postJson('/api/vouchers/'.$voucher->id.'/claim')->assertCreated()->json('claim.id');
        $quote = $this->postJson('/api/orders/preview', ['voucher_claim_id' => $id])->assertOk()->json('quote');
        $this->assertEquals(Order::SHIPPING_FEE_PER_SELLER, $quote['shipping_discount']);
        $response = $this->postJson('/api/orders/checkout', ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery', 'voucher_claim_id' => $id])->assertCreated();
        $this->assertEquals($quote['total'], $response->json('order.total_amount'));
        $this->assertDatabaseCount('deliveries', 2);
    }

    public function test_mobile_seller_application_is_saved_in_the_same_store_table(): void
    {
        Storage::fake('public');
        $user = $this->buyer();
        Sanctum::actingAs($user);
        $image = fn () => UploadedFile::fake()->createWithContent('document.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII='));
        $this->postJson('/api/seller/apply', [
            'first_name' => 'Mobile', 'last_name' => 'Buyer', 'email' => $user->email, 'phone' => '09171234567',
            'business_name' => 'Mobile Market', 'line_of_business' => 'Electronics', 'seller_type' => 'Sole Proprietorship', 'tin' => '123456789012',
            'region' => 'Metro Manila', 'province' => 'Metro Manila', 'city' => 'Manila', 'barangay' => 'Test Barangay', 'address_line' => '1 Test Street', 'postal_code' => '1000', 'terms' => true,
            'id_image' => $image(), 'dti_certificate' => $image(), 'bir_2303' => $image(),
        ])->assertCreated()->assertJsonPath('seller.user_id', $user->id)->assertJsonPath('seller.approval_status', 'pending');
        $this->assertDatabaseCount('sellers', 1);
        $this->assertSame('buyer', $user->fresh()->role);
        $this->getJson('/api/seller/status')->assertJsonPath('seller.business_name', 'Mobile Market');
    }

    public function test_delivered_mobile_order_can_be_rated_and_preserves_line_totals_and_images(): void
    {
        $user = $this->buyer();
        $product = $this->product();
        $this->line($user, $product, 2);
        $address = $this->address($user);
        Sanctum::actingAs($user);
        $id = $this->postJson('/api/orders/checkout', ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery'])->assertCreated()->json('order.id');
        Order::findOrFail($id)->update(['status' => 'delivered']);
        $this->postJson('/api/orders/'.$id.'/rate', ['product_id' => $product->id, 'rating' => 5, 'comment' => 'Works well'])->assertOk();
        $this->getJson('/api/orders/'.$id)->assertJsonPath('order.items.0.subtotal', 200)->assertJsonPath('order.items.0.product_image', 'products/test.png');
        $this->assertDatabaseHas('reviews', ['buyer_id' => $user->id, 'product_id' => $product->id, 'rating' => 5]);
    }
}
