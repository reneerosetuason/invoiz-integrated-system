<?php

namespace Tests\Feature;

use App\Models\{Address, Cart, CartItem, Delivery, Order, OrderItem, OrderStatusHistory, Payment, PaymentTransaction, Product, ProductVariant, Remittance, Seller, User};
use App\Support\{AssetFiles, Payments, Remittances};
use Illuminate\Support\Facades\{DB, Mail, Schema, Storage};
use Illuminate\Database\Schema\Blueprint;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class BuyerSecurityRegressionTest extends TestCase
{
    use BuyerMobileDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        Schema::create('seller_notifications', function (Blueprint $t) {
            $t->id(); $t->integer('seller_id'); $t->integer('order_id')->nullable(); $t->integer('product_id')->nullable();
            $t->string('type'); $t->string('title'); $t->text('body')->nullable(); $t->boolean('is_read')->default(false); $t->timestamps();
        });
        Schema::create('remittances', function (Blueprint $t) {
            $t->id(); $t->integer('order_id'); $t->integer('delivery_id')->nullable();
            $t->integer('seller_id'); $t->integer('rider_id')->nullable();
            $t->decimal('amount', 12, 2)->default(0); $t->string('status');
            $t->string('reference')->nullable(); $t->timestamp('confirmed_at')->nullable();
            $t->timestamp('sent_at')->nullable(); $t->text('note')->nullable(); $t->timestamps();
        });
        Schema::table('payments', fn (Blueprint $t) => $t->timestamp('paid_at')->nullable());
    }

    private function user(string $email = 'buyer@security.test', array $extra = []): User
    {
        return User::create(array_merge(['name' => 'Security Buyer', 'email' => $email, 'password' => 'Password123!', 'role' => 'buyer', 'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()], $extra));
    }

    private function product(): Product
    {
        $seller = $this->user('seller@security.test', ['role' => 'seller']);
        Seller::create(['user_id' => $seller->id, 'store_name' => 'Fixture store', 'approval_status' => 'approved', 'status' => 'active', 'address_line' => '1 Test St', 'barangay' => 'Test', 'city' => 'Manila', 'province' => 'Metro Manila']);
        return Product::create(['seller_id' => $seller->id, 'name' => 'Fixture item', 'stock' => 500, 'price' => 100, 'status' => 'active']);
    }

    private function address(User $user): Address
    {
        return Address::create(['buyer_id' => $user->id, 'recipient_name' => 'Fixture', 'phone' => '09171234567', 'address_line' => '2 Test St', 'barangay' => 'Test', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_default' => true]);
    }

    public function test_public_storage_is_confined_and_private_documents_require_ownership(): void
    {
        Storage::fake('public'); Storage::fake('private');
        Storage::disk('public')->put('products/security-canary.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+k7FYAAAAASUVORK5CYII='));
        Storage::disk('public')->put('ids/security-canary.png', 'synthetic public copy');
        Storage::disk('private')->put('ids/security-canary.png', 'synthetic private document');
        Storage::disk('public')->put('products/security-canary.php', '<?php echo "canary";');
        $this->get('/storage/products/security-canary.png')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        foreach (['../composer.json', '..\\composer.json', '%2e%2e/composer.json', 'C:/test.png', 'products/x.png:alternate', 'products/x.png/..', 'ids/security-canary.png'] as $path) {
            $this->assertNull(AssetFiles::publicFile($path), $path);
        }
        $this->get('/storage/ids/security-canary.png')->assertNotFound();
        $this->get('/storage/products/security-canary.php')->assertNotFound();
        Storage::disk('public')->put('products/html-disguised-as-image.png', '<html><p>Harmless fixture</p></html>');
        $this->get('/storage/products/html-disguised-as-image.png')->assertNotFound();
        $this->get('/storage/%252e%252e/composer.json')->assertNotFound();
        $this->getJson('/documents/ids/security-canary.png')->assertUnauthorized();
        $other = $this->user('other@security.test');
        Sanctum::actingAs($other);
        $this->getJson('/documents/ids/security-canary.png')->assertNotFound();
        $owner = $this->user(extra: ['id_image' => 'ids/security-canary.png']);
        Sanctum::actingAs($owner);
        $this->get('/documents/ids/security-canary.png')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        Sanctum::actingAs($this->user('admin@security.test', ['role' => 'admin', 'is_admin' => true]));
        $this->get('/documents/ids/security-canary.png')->assertOk();
    }

    public function test_document_migration_preserves_bytes_and_removes_both_public_copies(): void
    {
        // Use a unique synthetic file; never inspect a real identity document.
        Storage::fake('private');
        $path = 'ids/audit-fixture-'.bin2hex(random_bytes(8)).'.txt';
        $source = storage_path('app/public/'.$path);
        if (! is_dir(dirname($source))) mkdir(dirname($source), 0755, true);
        file_put_contents($source, 'document migration fixture');
        // Isolate the command's filesystem roots from the real uploads.
        $originalStorage = app()->storagePath();
        $originalPublic = public_path();
        $fixtureRoot = sys_get_temp_dir().'/invoiz-document-audit-'.bin2hex(random_bytes(8));
        mkdir($fixtureRoot.'/storage/app/public/ids', 0755, true);
        mkdir($fixtureRoot.'/public/storage/ids', 0755, true);
        $a = $fixtureRoot.'/storage/app/public/'.$path;
        $b = $fixtureRoot.'/public/storage/'.$path;
        copy($source, $a); copy($source, $b); unlink($source);
        try {
            app()->useStoragePath($fixtureRoot.'/storage'); app()->usePublicPath($fixtureRoot.'/public');
            $this->artisan('documents:privatize', ['--apply' => true])->assertSuccessful();
            $this->assertFileDoesNotExist($a); $this->assertFileDoesNotExist($b);
            $this->assertSame('document migration fixture', Storage::disk('private')->get($path));
        } finally {
            app()->useStoragePath($originalStorage); app()->usePublicPath($originalPublic);
            \Illuminate\Support\Facades\File::deleteDirectory($fixtureRoot);
        }
    }

    public function test_legacy_registration_has_no_token_and_requires_email_verification(): void
    {
        $data = ['first_name' => 'Test', 'last_name' => 'Customer', 'sex' => 'other', 'email' => 'new@security.test', 'password' => 'Password123!', 'password_confirmation' => 'Password123!', 'phone' => '09171234567', 'birthday' => '2000-01-01'];
        $this->postJson('/api/register', $data)->assertCreated()->assertJsonPath('verification_required', true)->assertJsonMissingPath('token');
        $this->assertNull(User::where('email', $data['email'])->firstOrFail()->email_verified_at);
        Mail::assertSent(\App\Mail\OtpCodeMail::class);
    }

    public function test_all_login_aliases_reject_an_unverified_account(): void
    {
        $user = $this->user(extra: ['email_verified_at' => null]);
        foreach (['/api/login', '/api/auth/login', '/api/mobile/buyer/login'] as $url) {
            $this->postJson($url, ['email' => $user->email, 'password' => 'Password123!'])->assertForbidden()->assertJsonPath('verification_required', true)->assertJsonMissingPath('token');
        }
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_aliases_share_the_attempt_limit(): void
    {
        $user = $this->user();
        for ($i = 0; $i < 5; $i++) {
            $path = $i % 2 === 0 ? '/api/auth/login' : '/api/login';
            $this->postJson($path, ['email' => $user->email, 'password' => 'Wrong-password'])->assertUnauthorized();
        }
        $this->postJson('/api/mobile/buyer/login', ['email' => $user->email, 'password' => 'Password123!'])->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_existing_tokens_cannot_bypass_verification_or_approval_or_role(): void
    {
        foreach ([['email_verified_at' => null], ['approval_status' => 'pending'], ['approval_status' => 'rejected'], ['role' => 'rider'], ['role' => 'logistics_staff']] as $i => $extra) {
            $user = $this->user("case{$i}@security.test", $extra);
            Sanctum::actingAs($user);
            $this->getJson('/api/cart')->assertForbidden()->assertJsonPath('code', 'buyer_access_denied');
            $this->getJson('/api/orders')->assertForbidden();
        }
        $this->assertDatabaseCount('orders', 0); $this->assertDatabaseCount('carts', 0);
    }

    public function test_password_change_checks_current_password_and_revokes_other_tokens(): void
    {
        $user = $this->user();
        $current = $user->createToken('current'); $user->createToken('other');
        $this->withToken($current->plainTextToken)->postJson('/api/profile', ['password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertUnprocessable();
        $this->assertDatabaseCount('personal_access_tokens', 2);
        $this->postJson('/api/profile', ['current_password' => 'incorrect', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertUnprocessable();
        $this->postJson('/api/profile', ['current_password' => 'Password123!', 'password' => 'NewPassword123!', 'password_confirmation' => 'NewPassword123!'])->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 1);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword123!', $user->fresh()->password));
    }

    public function test_store_pagination_validates_invalid_or_unbounded_values(): void
    {
        $product = $this->product();
        foreach (['per_page=-1', 'per_page=100000', 'page=-1', 'search[]=invalid'] as $query) {
            $this->getJson('/api/stores/'.$product->seller_id.'?'.$query)->assertUnprocessable();
            $this->getJson('/api/products?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/stores/'.$product->seller_id.'/reviews?per_page=0')->assertUnprocessable();
    }

    public function test_cart_requires_a_variation_and_honors_combined_cart_limit(): void
    {
        $user = $this->user(); $product = $this->product(); Sanctum::actingAs($user);
        $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 201])->assertUnprocessable();
        $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 199])->assertOk();
        $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 2])->assertUnprocessable();
        $this->assertSame(199, (int) CartItem::first()->quantity);
        $this->deleteJson('/api/cart')->assertOk();
        ProductVariant::create(['product_id' => $product->id, 'stock' => 3, 'status' => 'active', 'variant_type' => 'Size', 'variant_value' => 'M']);
        $this->postJson('/api/cart/add', ['product_id' => $product->id, 'quantity' => 1])->assertUnprocessable();
    }

    public function test_deleted_product_and_disabled_variant_do_not_break_cart_or_allow_updates(): void
    {
        $user = $this->user(); $product = $this->product();
        $variant = ProductVariant::create(['product_id' => $product->id, 'stock' => 3, 'status' => 'active']);
        $line = CartItem::create(['cart_id' => Cart::headerFor($user->id)->id, 'product_id' => $product->id, 'variant_id' => $variant->id, 'quantity' => 1]);
        $variant->update(['status' => 'inactive']); Sanctum::actingAs($user);
        $this->putJson('/api/cart/'.$line->id, ['quantity' => 2])->assertUnprocessable();
        $product->delete();
        $this->getJson('/api/cart')->assertOk()->assertJsonPath('cart.items.0.available_stock', 0);
        $this->putJson('/api/cart/'.$line->id, ['quantity' => 2])->assertUnprocessable();
        $this->deleteJson('/api/cart/'.$line->id)->assertOk();
    }

    public function test_checkout_retry_does_not_duplicate_the_order(): void
    {
        $user = $this->user(); $product = $this->product(); $address = $this->address($user);
        CartItem::create(['cart_id' => Cart::headerFor($user->id)->id, 'product_id' => $product->id, 'quantity' => 2]);
        Sanctum::actingAs($user);
        $request = ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery'];
        $this->postJson('/api/orders/checkout', $request)->assertCreated();
        $this->postJson('/api/orders/checkout', $request)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1); $this->assertDatabaseCount('payment_transactions', 1);
        $this->assertSame(498, $product->fresh()->stock);
    }

    public function test_ledger_failure_rolls_back_checkout_and_does_not_leak_exception_details(): void
    {
        $user = $this->user(); $product = $this->product(); $address = $this->address($user);
        CartItem::create(['cart_id' => Cart::headerFor($user->id)->id, 'product_id' => $product->id, 'quantity' => 1]);
        $fail = true;
        PaymentTransaction::creating(function () use (&$fail) { if ($fail) throw new \RuntimeException('private-canary-database-detail'); });
        Sanctum::actingAs($user);
        $data = ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery'];
        $this->postJson('/api/orders/checkout', $data)->assertStatus(500)->assertDontSee('private-canary-database-detail');
        $this->assertDatabaseCount('orders', 0); $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('cart_items', 1); $this->assertSame(500, $product->fresh()->stock);
        $fail = false;
        $this->postJson('/api/orders/checkout', $data)->assertCreated();
    }

    public function test_returns_identify_the_purchased_variant_and_do_not_extend_with_order_edits(): void
    {
        $user = $this->user(); $product = $this->product(); Sanctum::actingAs($user);
        $order = Order::create(['buyer_id' => $user->id, 'status' => 'delivered', 'total_amount' => 200]);
        $a = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'seller_id' => $product->seller_id, 'quantity' => 1, 'price' => 100, 'variant_label' => 'Size M']);
        $b = OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'seller_id' => $product->seller_id, 'quantity' => 1, 'price' => 100, 'variant_label' => 'Size L']);
        $history = OrderStatusHistory::create(['order_id' => $order->id, 'to_status' => 'delivered', 'created_at' => now()]);
        $this->postJson('/api/orders/'.$order->id.'/returns', ['product_id' => $product->id, 'reason' => 'wrong_item'])->assertUnprocessable();
        $this->postJson('/api/orders/'.$order->id.'/returns', ['order_item_id' => $b->id, 'reason' => 'wrong_item'])->assertCreated()->assertJsonPath('return.order_item_id', $b->id);
        DB::table('order_status_histories')->where('id', $history->id)->update(['created_at' => now()->subDays(8)]);
        $order->touch();
        $this->postJson('/api/orders/'.$order->id.'/returns', ['order_item_id' => $a->id, 'reason' => 'wrong_item'])->assertUnprocessable()->assertJsonPath('message', 'The return window (7 days after delivery) has closed.');
    }

    public function test_colliding_seller_ids_cannot_confirm_another_stores_remittance(): void
    {
        $a = $this->user('a@security.test', ['role' => 'seller']);
        $b = $this->user('b@security.test', ['role' => 'seller']);
        $storeA = Seller::create(['user_id' => $a->id, 'store_name' => 'A', 'approval_status' => 'approved', 'status' => 'active']);
        DB::table('sellers')->where('id', $storeA->id)->update(['id' => $b->id]);
        $storeB = Seller::create(['user_id' => $b->id, 'store_name' => 'B', 'approval_status' => 'approved', 'status' => 'active']);
        DB::table('sellers')->where('id', $storeB->id)->update(['id' => 1000]);
        $this->assertSame($b->id, Seller::where('user_id', $a->id)->firstOrFail()->id);
        $order = Order::create(['buyer_id' => 100, 'seller_id' => $b->id, 'total_amount' => 150]);
        $remittance = Remittance::create(['order_id' => $order->id, 'seller_id' => $b->id, 'status' => 'pending', 'amount' => 100]);
        $this->actingAs($a)->withSession(['active_role' => 'seller'])->postJson('/seller/remittances/'.$remittance->id.'/confirm')->assertNotFound();
        $this->assertSame('pending', $remittance->fresh()->status);
        $this->assertSame([$a->id], Remittances::sellerKeys($a->id));
        $this->assertSame([$a->id], Payments::catalogIdsForUser($a->id));
    }

    public function test_failed_cash_settlement_rolls_back_confirmation_and_allows_a_retry(): void
    {
        $product = $this->product();
        $order = Order::create(['buyer_id' => 100, 'seller_id' => $product->seller_id, 'total_amount' => 150, 'status' => 'delivered', 'payment_status' => 'unpaid']);
        OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'seller_id' => $product->seller_id, 'quantity' => 1, 'price' => 100]);
        Payment::create(['order_id' => $order->id, 'amount' => 150, 'status' => 'pending', 'method' => 'cash_on_delivery']);
        $delivery = Delivery::create(['order_id' => $order->id, 'seller_id' => $product->seller_id, 'status' => 'delivered']);
        $remittance = Remittance::create(['order_id' => $order->id, 'delivery_id' => $delivery->id, 'seller_id' => $product->seller_id, 'status' => 'pending', 'amount' => 100]);
        $fail = true;
        PaymentTransaction::creating(function () use (&$fail) { if ($fail) throw new \RuntimeException('ledger fixture failure'); });
        try { Remittances::confirm($remittance, [$product->seller_id]); $this->fail('Expected ledger failure.'); }
        catch (\RuntimeException $e) { $this->assertSame('ledger fixture failure', $e->getMessage()); }
        $this->assertSame('pending', $remittance->fresh()->status); $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertDatabaseCount('payment_transactions', 0);
        $fail = false;
        $this->assertTrue(Remittances::confirm($remittance, [$product->seller_id]));
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertFalse(Remittances::confirm($remittance->fresh(), [$product->seller_id]));
        $this->assertDatabaseCount('payment_transactions', 1);
    }

    public function test_chat_rejects_foreign_arbitrary_recipient_ids_and_self_messages(): void
    {
        $user = $this->user(); $other = $this->user('other@security.test'); Sanctum::actingAs($user);
        foreach ([$user->id, $other->id, 999999] as $id) {
            $this->postJson('/api/conversations', ['seller_id' => $id, 'subject' => 'Fixture', 'body' => 'Test'])->assertUnprocessable();
        }
        $this->assertDatabaseCount('messages', 0);
    }

    public function test_notification_read_alias_cannot_record_nonexistent_or_foreign_notifications(): void
    {
        Sanctum::actingAs($this->user());
        $this->postJson('/api/notifications/read', ['id' => 'order-999999'])->assertNotFound();
        $this->assertDatabaseCount('notification_reads', 0);
    }
    public function test_a_store_application_does_not_bypass_pending_buyer_approval(): void
    {
        $user = $this->user(extra: ['approval_status' => 'pending']);
        Seller::create(['user_id' => $user->id, 'store_name' => 'Pending store', 'approval_status' => 'pending', 'status' => 'active']);
        Sanctum::actingAs($user);
        $this->getJson('/api/cart')->assertForbidden();
        auth()->forgetGuards();
        $this->postJson('/api/mobile/buyer/login', ['email' => $user->email, 'password' => 'Password123!'])->assertForbidden()->assertJsonMissingPath('token');
    }

    public function test_suspended_or_unapproved_stores_are_hidden_and_cannot_be_checked_out(): void
    {
        $buyer = $this->user(); $product = $this->product();
        $seller = Seller::where('user_id', $product->seller_id)->firstOrFail();
        $seller->update(['approval_status' => 'pending']);
        $this->getJson('/api/products')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/products/'.$product->id)->assertNotFound();
        $this->getJson('/api/stores/'.$product->seller_id)->assertNotFound();
        $seller->update(['approval_status' => 'approved']);
        $seller->user->update(['account_status' => 'suspended']);
        $this->getJson('/api/products')->assertJsonCount(0, 'data');
        $this->getJson('/api/stores/'.$product->seller_id.'/reviews')->assertNotFound();
        CartItem::create(['cart_id' => Cart::headerFor($buyer->id)->id, 'product_id' => $product->id, 'quantity' => 1]);
        Sanctum::actingAs($buyer);
        $this->postJson('/api/orders/preview')->assertUnprocessable();
    }

    public function test_settlement_waits_for_every_parcel_not_just_the_first_delivered_one(): void
    {
        $product = $this->product();
        $order = Order::create(['buyer_id' => 100, 'total_amount' => 300]);
        $delivery = Delivery::create(['order_id' => $order->id, 'seller_id' => $product->seller_id, 'status' => 'delivered']);
        Delivery::create(['order_id' => $order->id, 'seller_id' => 999, 'status' => 'out_for_delivery']);
        Remittance::create(['order_id' => $order->id, 'delivery_id' => $delivery->id, 'seller_id' => $product->seller_id, 'status' => 'confirmed', 'amount' => 100]);
        $this->assertFalse(Remittances::allDeliveredConfirmed($order->id));
    }

    public function test_pending_buyer_can_verify_on_the_website_without_getting_a_session(): void
    {
        $user = $this->user(extra: ['approval_status' => 'pending', 'email_verified_at' => null, 'otp' => '123456', 'otp_expires_at' => now()->addMinutes(10)]);
        $this->post('/verify', ['email' => $user->email, 'code' => '123456'])->assertRedirect('/login');
        $this->assertNotNull($user->fresh()->email_verified_at);
        $this->assertGuest();
    }

    public function test_application_status_lookup_does_not_reveal_details_from_an_email_alone(): void
    {
        // These application tables intentionally do not exist in this fixture.
        // The privacy gate must reject the lookup before querying them.
        $this->getJson('/api/logistics/rider/application-status?email=applicant@example.test')->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->getJson('/api/logistics/center/application-status?email=applicant@example.test')->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->post('/logistics/logistics-center/application-status', ['email' => 'applicant@example.test'])->assertSessionHasErrors('email');
        Sanctum::actingAs($this->user());
        $this->getJson('/api/logistics/rider/application-status?email=someone-else@example.test')->assertUnprocessable();
    }

    public function test_application_status_proof_is_secret_email_bound_and_expires(): void
    {
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => null);
        $token = \App\Support\ApplicationStatusAccess::grant($request, 'applicant@example.test');
        $this->assertSame(64, strlen($token));
        $request->headers->set('X-Application-Access', $token);
        $this->assertTrue(\App\Support\ApplicationStatusAccess::allowed($request, 'applicant@example.test'));
        $this->assertFalse(\App\Support\ApplicationStatusAccess::allowed($request, 'other@example.test'));
        $this->travel(16)->minutes();
        $this->assertFalse(\App\Support\ApplicationStatusAccess::allowed($request, 'applicant@example.test'));
    }

    public function test_status_page_includes_verification_and_keeps_internal_details_hidden_until_verified(): void
    {
        $this->get('/logistics/logistics-center/application-status')->assertOk()->assertSee('Send verification code')->assertSee('application-status.js');
    }

    public function test_voucher_ownership_distinguishes_colliding_ids_and_preserves_unambiguous_legacy_keys(): void
    {
        $a = $this->user('a@voucher.test', ['role' => 'seller']);
        $b = $this->user('b@voucher.test', ['role' => 'seller']);
        $storeA = Seller::create(['user_id' => $a->id, 'store_name' => 'A', 'approval_status' => 'approved', 'status' => 'active']);
        DB::table('sellers')->where('id', $storeA->id)->update(['id' => $b->id]);
        $storeA = Seller::findOrFail($b->id);
        $storeB = Seller::create(['user_id' => $b->id, 'store_name' => 'B', 'approval_status' => 'approved', 'status' => 'active']);
        DB::table('sellers')->where('id', $storeB->id)->update(['id' => 1000]);
        $storeB = Seller::findOrFail(1000);
        $this->assertSame($b->id, $storeA->id);
        $voucher = \App\Models\Voucher::create(['seller_id' => $storeA->id, 'code' => 'STOREA', 'name' => 'Store A offer', 'discount_type' => 'fixed', 'discount_value' => 10, 'status' => 'active']);
        $this->actingAs($b)->withSession(['active_role' => 'seller'])->post('/seller/vouchers/'.$voucher->id, ['status' => 'inactive'])->assertNotFound();
        $this->assertNotContains($a->id, $storeB->catalogIds());
        $this->assertNotContains($b->id, $storeB->recordIds());
        // No seller row has key A's user ID, so this old-format voucher is unambiguous.
        $legacy = \App\Models\Voucher::create(['seller_id' => $a->id, 'code' => 'LEGACY', 'name' => 'Legacy offer', 'discount_type' => 'fixed', 'discount_value' => 10, 'status' => 'active']);
        \App\Models\VoucherClaim::create(['voucher_id' => $legacy->id, 'buyer_id' => $a->id, 'claimed_at' => now()]);
        $this->actingAs($a)->withSession(['active_role' => 'seller'])->post('/seller/vouchers/'.$legacy->id, ['status' => 'inactive'])->assertRedirect();
        $this->assertSame('inactive', $legacy->fresh()->status);
    }

}
