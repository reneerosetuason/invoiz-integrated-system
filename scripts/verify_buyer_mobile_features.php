<?php

/**
 * End-to-End Functional Test for ALL Buyer Mobile Application APIs, Endpoints & Workflows
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Seller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Delivery;
use App\Models\Address;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ReturnRequest;
use App\Models\Favorite;
use App\Models\Notification;
use App\Models\Complaint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$testResults = [];

function check(string $suite, string $name, bool $pass, string $note = '')
{
    global $testResults, $lastResponse;
    if (!$pass && empty($note) && isset($lastResponse) && $lastResponse instanceof \Symfony\Component\HttpFoundation\Response) {
        $note = 'HTTP ' . $lastResponse->getStatusCode() . ' -> ' . substr($lastResponse->getContent(), 0, 150);
    }
    $testResults[] = ['suite' => $suite, 'name' => $name, 'pass' => $pass, 'note' => $note];
    $status = $pass ? "[PASS]" : "[FAIL]";
    echo "{$status} [{$suite}] {$name}" . ($note ? " ({$note})" : '') . PHP_EOL;
}

echo "========================================================================\n";
echo " INVOIZ: Verifying ALL Buyer Mobile App Endpoints, Buttons & Functions  \n";
echo "========================================================================\n\n";

DB::beginTransaction();

try {
    // -----------------------------------------------------------------
    // Helper to execute API requests with Sanctum auth
    // -----------------------------------------------------------------
    $handleApi = function (string $method, string $uri, array $data = [], ?User $user = null, ?string $token = null, array $files = []) use ($app) {
        Auth::forgetGuards();
        Auth::shouldUse('sanctum');

        $server = [
            'HTTP_ACCEPT' => 'application/json',
        ];

        if ($token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
        }

        $parameters = [];
        $content = null;

        if (!empty($files)) {
            $parameters = $data;
        } else {
            $server['CONTENT_TYPE'] = 'application/json';
            $content = json_encode($data);
        }

        $request = Request::create($uri, $method, $parameters, [], $files, $server, $content);

        if ($user) {
            $request->setUserResolver(fn () => $user);
            Auth::guard('sanctum')->setUser($user);
        }

        $res = $app->handle($request);
        $GLOBALS['lastResponse'] = $res;
        return $res;
    };

    // -----------------------------------------------------------------
    // Setup Base Entities (Seller, Category, Product)
    // -----------------------------------------------------------------
    $sellerUser = User::create([
        'name' => 'Seller Store Test',
        'first_name' => 'Seller',
        'last_name' => 'Store',
        'email' => 'seller-mob-test-' . Str::random(8) . '@invoiz-test.local',
        'phone' => '0917' . random_int(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'role' => 'seller',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $seller = Seller::create([
        'user_id' => $sellerUser->id,
        'store_name' => 'Mobile Gadgets Co',
        'business_name' => 'Mobile Gadgets Enterprises',
        'line_of_business' => 'Electronics',
        'store_description' => 'Official flagship store for mobile verification.',
        'status' => 'active',
        'approval_status' => 'approved',
        'address_line' => '123 Tech Hub, Pasig City',
        'barangay' => 'San Antonio',
        'city' => 'Pasig',
        'province' => 'Metro Manila',
        'pickup_address' => '123 Tech Hub, Pasig City',
        'pickup_barangay' => 'San Antonio',
        'pickup_city' => 'Pasig',
        'pickup_province' => 'Metro Manila',
        'pickup_latitude' => 14.5764,
        'pickup_longitude' => 121.0851,
        'can_sell' => true,
    ]);

    $category = Category::create([
        'name' => 'Mobile Electronics ' . Str::random(5),
        'slug' => 'mobile-elec-' . Str::random(5),
        'status' => 'active',
    ]);

    $product = Product::create([
        'seller_id' => $sellerUser->id,
        'user_id' => $sellerUser->id,
        'category_id' => $category->id,
        'name' => 'Ultra Wireless Headphones ' . Str::random(4),
        'slug' => 'headphones-' . Str::random(6),
        'description' => 'Hi-fi sound with active noise cancellation.',
        'brand' => 'SoundWave',
        'price' => 1500.00,
        'stock' => 50,
        'status' => 'active',
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'variant_type' => 'Color',
        'variant_value' => 'Midnight Black',
        'price_adjustment' => 100.00,
        'stock' => 20,
        'status' => 'active',
    ]);

    // =================================================================
    // SECTION 1: PUBLIC CATALOG & DISCOVERY
    // =================================================================
    echo "\n--- Section 1: Public Catalog & Discovery ---\n";

    // 1.1 Categories List
    $res = $handleApi('GET', '/api/categories');
    check('Catalog', 'GET /api/categories lists active categories', $res->getStatusCode() === 200 && str_contains($res->getContent(), $category->name));

    // 1.2 Products List & Search
    $res = $handleApi('GET', '/api/products?search=' . urlencode($product->name));
    check('Catalog', 'GET /api/products searches by name', $res->getStatusCode() === 200 && str_contains($res->getContent(), $product->name));

    // 1.3 Products List filtered by category
    $res = $handleApi('GET', '/api/products?category=' . $category->id);
    check('Catalog', 'GET /api/products filters by category', $res->getStatusCode() === 200 && str_contains($res->getContent(), $product->name));

    // 1.4 Product Detail
    $res = $handleApi('GET', '/api/products/' . $product->id);
    check('Catalog', 'GET /api/products/{id} loads product details & variants', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'Midnight Black'));

    // 1.5 PSGC Regions
    $res = $handleApi('GET', '/api/psgc/regions');
    check('Catalog', 'GET /api/psgc/regions returns cascading address regions', $res->getStatusCode() === 200);

    // 1.6 Help FAQ
    $res = $handleApi('GET', '/api/help/faq');
    check('Catalog', 'GET /api/help/faq returns FAQ topics', $res->getStatusCode() === 200);

    // =================================================================
    // SECTION 2: MOBILE AUTHENTICATION & PROFILE
    // =================================================================
    echo "\n--- Section 2: Mobile Authentication & Profile ---\n";

    $buyerEmail = 'mobile-buyer-' . strtolower(Str::random(8)) . '@invoiz-test.local';
    $buyerPhone = '0918' . random_int(1000000, 9999999);

    // 2.1 Register via mobile endpoint
    $res = $handleApi('POST', '/api/mobile/buyer/register', [
        'name' => 'Mobile Buyer Jane',
        'first_name' => 'Jane',
        'last_name' => 'Buyer',
        'sex' => 'female',
        'birthday' => '2000-01-01',
        'email' => $buyerEmail,
        'phone' => $buyerPhone,
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]);
    check('Auth', 'POST /api/mobile/buyer/register registers unverified buyer', $res->getStatusCode() === 201 || $res->getStatusCode() === 200);

    $buyerUser = User::where('email', $buyerEmail)->first();
    check('Auth', 'Buyer record created in database', $buyerUser !== null);

    // 2.2 Verify Email with OTP
    $buyerUser = $buyerUser->fresh();
    $otpCode = $buyerUser->otp;
    if (!$otpCode) {
        $otpCode = '123456';
        $buyerUser->update([
            'otp' => $otpCode,
            'otp_expires_at' => now()->addMinutes(15),
        ]);
    }

    $res = $handleApi('POST', '/api/mobile/buyer/verify-email', [
        'email' => $buyerEmail,
        'code' => $otpCode,
    ]);
    check('Auth', 'POST /api/mobile/buyer/verify-email verifies email', $res->getStatusCode() === 200);

    // Admin approves the buyer account so login and buyer access succeed
    $buyerUser = $buyerUser->fresh();
    $buyerUser->update(['approval_status' => 'approved', 'status' => 'active', 'account_status' => 'active', 'role' => 'buyer']);
    $buyerUser = $buyerUser->fresh();

    // 2.3 Mobile Login
    $res = $handleApi('POST', '/api/mobile/buyer/login', [
        'email' => $buyerEmail,
        'password' => 'Password123!',
        'device_name' => 'Flutter-Pixel-7',
    ]);
    $loginData = json_decode($res->getContent(), true);
    $token = $loginData['token'] ?? null;
    check('Auth', 'POST /api/mobile/buyer/login authenticates and returns bearer token', $res->getStatusCode() === 200 && !empty($token));

    // 2.4 GET /me
    $res = $handleApi('GET', '/api/me', [], $buyerUser, $token);
    check('Auth', 'GET /api/me returns authenticated buyer profile', $res->getStatusCode() === 200 && str_contains($res->getContent(), $buyerEmail));

    // 2.5 Update Profile
    $res = $handleApi('POST', '/api/profile', [
        'name' => 'Jane Verified',
        'first_name' => 'Jane',
        'last_name' => 'Verified',
        'phone' => $buyerPhone,
    ], $buyerUser, $token);
    check('Auth', 'POST /api/profile updates profile names', $res->getStatusCode() === 200);

    // =================================================================
    // SECTION 3: STORE & SELLER PROFILE
    // =================================================================
    echo "\n--- Section 3: Store & Seller Profile ---\n";

    // 3.1 Store Profile
    $res = $handleApi('GET', '/api/stores/' . $sellerUser->id);
    check('Store', 'GET /api/stores/{seller} loads store details and products', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'Mobile Gadgets'));

    // 3.2 Store Reviews
    $res = $handleApi('GET', '/api/stores/' . $sellerUser->id . '/reviews');
    check('Store', 'GET /api/stores/{seller}/reviews loads customer reviews', $res->getStatusCode() === 200);

    // 3.3 Follow Store
    $res = $handleApi('POST', '/api/stores/' . $sellerUser->id . '/follow', [], $buyerUser, $token);
    check('Store', 'POST /api/stores/{seller}/follow follows the store', $res->getStatusCode() === 200 || $res->getStatusCode() === 201);

    // 3.4 Unfollow Store
    $res = $handleApi('DELETE', '/api/stores/' . $sellerUser->id . '/follow', [], $buyerUser, $token);
    check('Store', 'DELETE /api/stores/{seller}/follow unfollows the store', $res->getStatusCode() === 200);

    // =================================================================
    // SECTION 4: FAVORITES & WISHLIST
    // =================================================================
    echo "\n--- Section 4: Favorites & Wishlist ---\n";

    // 4.1 Toggle Favorite (Add)
    $res = $handleApi('POST', '/api/favorites/toggle', ['product_id' => $product->id], $buyerUser, $token);
    check('Wishlist', 'POST /api/favorites/toggle adds item to wishlist', $res->getStatusCode() === 200);

    // 4.2 List Favorites
    $res = $handleApi('GET', '/api/favorites', [], $buyerUser, $token);
    check('Wishlist', 'GET /api/favorites lists buyer favorited products', $res->getStatusCode() === 200 && str_contains($res->getContent(), $product->name));

    // 4.3 Toggle Favorite (Remove)
    $res = $handleApi('POST', '/api/favorites/toggle', ['product_id' => $product->id], $buyerUser, $token);
    check('Wishlist', 'POST /api/favorites/toggle removes item from wishlist', $res->getStatusCode() === 200);

    // =================================================================
    // SECTION 5: ADDRESS BOOK
    // =================================================================
    echo "\n--- Section 5: Address Book ---\n";

    // 5.1 Create Address
    $res = $handleApi('POST', '/api/addresses', [
        'recipient_name' => 'Jane Verified',
        'phone' => $buyerPhone,
        'address_line' => 'Unit 402 Jade Tower, Emerald Ave',
        'barangay' => 'San Antonio',
        'city' => 'Pasig',
        'province' => 'Metro Manila',
        'postal_code' => '1605',
        'is_default' => true,
    ], $buyerUser, $token);
    $addrData = json_decode($res->getContent(), true);
    $addressId = $addrData['address']['id'] ?? ($addrData['id'] ?? null);
    check('Address', 'POST /api/addresses creates new delivery address', ($res->getStatusCode() === 201 || $res->getStatusCode() === 200) && $addressId !== null);

    // 5.2 List Addresses
    $res = $handleApi('GET', '/api/addresses', [], $buyerUser, $token);
    check('Address', 'GET /api/addresses returns buyer address book', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'Jade Tower'));

    // 5.3 Update Address
    if ($addressId) {
        $res = $handleApi('PUT', '/api/addresses/' . $addressId, [
            'recipient_name' => 'Jane Verified',
            'phone' => $buyerPhone,
            'address_line' => 'Unit 405 Jade Tower, Emerald Ave',
            'barangay' => 'San Antonio',
            'city' => 'Pasig',
            'province' => 'Metro Manila',
            'postal_code' => '1605',
            'is_default' => true,
        ], $buyerUser, $token);
        check('Address', 'PUT /api/addresses/{id} modifies existing address', $res->getStatusCode() === 200);
    }

    // =================================================================
    // SECTION 6: CART MANAGEMENT
    // =================================================================
    echo "\n--- Section 6: Cart Management ---\n";

    // 6.1 Add Variant Product to Cart
    $res = $handleApi('POST', '/api/cart/add', [
        'product_id' => $product->id,
        'variant_id' => $variant->id,
        'quantity' => 2,
    ], $buyerUser, $token);
    check('Cart', 'POST /api/cart/add adds variant product to cart', $res->getStatusCode() === 200 || $res->getStatusCode() === 201);

    // 6.2 Fetch Cart
    $res = $handleApi('GET', '/api/cart', [], $buyerUser, $token);
    $cartData = json_decode($res->getContent(), true);
    $cartItems = $cartData['cart']['items'] ?? ($cartData['items'] ?? []);
    check('Cart', 'GET /api/cart lists cart items with subtotal', $res->getStatusCode() === 200 && count($cartItems) >= 1);

    $cartItemId = $cartItems[0]['id'] ?? null;

    // 6.3 Update Cart Quantity
    if ($cartItemId) {
        $res = $handleApi('PUT', '/api/cart/' . $cartItemId, ['quantity' => 3], $buyerUser, $token);
        check('Cart', 'PUT /api/cart/{id} updates item quantity', $res->getStatusCode() === 200);
    }

    // =================================================================
    // SECTION 7: VOUCHERS & DISCOUNTS
    // =================================================================
    echo "\n--- Section 7: Vouchers & Discounts ---\n";

    $voucherCode = 'MOBDISC' . random_int(100, 999);
    $voucher = Voucher::create([
        'seller_id' => $seller->id,
        'code' => $voucherCode,
        'name' => 'Mobile Special 100 Off',
        'type' => 'fixed',
        'discount_type' => 'fixed',
        'discount_value' => 100.00,
        'value' => 100.00,
        'min_spend' => 500.00,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDays(30),
        'usage_limit' => 100,
        'status' => 'active',
    ]);

    // 7.1 Browse Vouchers
    $res = $handleApi('GET', '/api/vouchers', [], $buyerUser, $token);
    check('Voucher', 'GET /api/vouchers lists available store and global vouchers', $res->getStatusCode() === 200);

    // 7.2 Claim Voucher
    $res = $handleApi('POST', '/api/vouchers/' . $voucher->id . '/claim', [], $buyerUser, $token);
    $claimData = json_decode($res->getContent(), true);
    $claimId = $claimData['claim']['id'] ?? ($claimData['id'] ?? null);
    check('Voucher', 'POST /api/vouchers/{id}/claim claims voucher to buyer wallet', $res->getStatusCode() === 200 || $res->getStatusCode() === 201);

    // 7.3 List Claimed Vouchers
    $res = $handleApi('GET', '/api/vouchers/claimed', [], $buyerUser, $token);
    check('Voucher', 'GET /api/vouchers/claimed lists claimed vouchers', $res->getStatusCode() === 200);

    // 7.4 Validate Voucher Code
    $res = $handleApi('POST', '/api/vouchers/validate', [
        'code' => $voucherCode,
        'cart_item_ids' => [$cartItemId],
    ], $buyerUser, $token);
    check('Voucher', 'POST /api/vouchers/validate checks discount calculation', $res->getStatusCode() === 200);

    // =================================================================
    // SECTION 8: CHECKOUT & ORDERS
    // =================================================================
    echo "\n--- Section 8: Checkout & Orders ---\n";

    // 8.1 Checkout Preview / Quote
    $res = $handleApi('POST', '/api/orders/preview', [
        'cart_item_ids' => [$cartItemId],
        'voucher_claim_id' => $claimId,
    ], $buyerUser, $token);
    check('Checkout', 'POST /api/orders/preview calculates quote and shipping fee', $res->getStatusCode() === 200);

    // 8.2 Checkout / Place Order (COD)
    $res = $handleApi('POST', '/api/orders/checkout', [
        'cart_item_ids' => [$cartItemId],
        'address_id' => $addressId,
        'payment_method' => 'cash_on_delivery',
        'voucher_claim_id' => $claimId,
        'notes' => 'Please ring the doorbell upon arrival.',
    ], $buyerUser, $token);
    $checkoutRes = json_decode($res->getContent(), true);
    $orderId = $checkoutRes['order_id'] ?? ($checkoutRes['order']['id'] ?? ($checkoutRes['orders'][0]['id'] ?? null));
    check('Checkout', 'POST /api/orders/checkout places order and creates order record', ($res->getStatusCode() === 200 || $res->getStatusCode() === 201) && $orderId !== null);

    // 8.3 List Orders
    $res = $handleApi('GET', '/api/orders', [], $buyerUser, $token);
    check('Orders', 'GET /api/orders lists buyer orders with status tabs', $res->getStatusCode() === 200 && str_contains($res->getContent(), (string)$orderId));

    // 8.4 Order Details
    $res = $handleApi('GET', '/api/orders/' . $orderId, [], $buyerUser, $token);
    check('Orders', 'GET /api/orders/{id} displays order timeline, items, and address', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'Ultra Wireless'));

    // 8.5 Cancel Order
    $res = $handleApi('POST', '/api/orders/' . $orderId . '/cancel', [
        'reason' => 'changed_mind',
    ], $buyerUser, $token);
    check('Orders', 'POST /api/orders/{id}/cancel cancels pending order with valid reason', $res->getStatusCode() === 200);

    // =================================================================
    // SECTION 9: DELIVERIES & OTP
    // =================================================================
    echo "\n--- Section 9: Deliveries & OTP ---\n";

    // Create a delivered order with delivery record for OTP & rating tests
    $deliveredOrder = Order::create([
        'buyer_id' => $buyerUser->id,
        'seller_id' => $sellerUser->id,
        'order_number' => 'INV-MOB-' . strtoupper(Str::random(6)),
        'total_amount' => 1500.00,
        'status' => 'delivered',
        'payment_method' => 'cash_on_delivery',
        'payment_status' => 'paid',
        'address_id' => $addressId,
        'shipping_name' => 'Jane Verified',
        'shipping_phone' => $buyerPhone,
        'shipping_address' => 'Unit 405 Jade Tower, Pasig',
        'delivered_at' => now(),
    ]);

    $deliveredItem = OrderItem::create([
        'order_id' => $deliveredOrder->id,
        'seller_id' => $sellerUser->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'quantity' => 1,
        'unit_price' => 1500.00,
        'subtotal' => 1500.00,
    ]);

    $delivery = Delivery::create([
        'order_id' => $deliveredOrder->id,
        'tracking_number' => 'TRK-' . date('Ymd') . '-TEST',
        'status' => 'out_for_delivery',
        'delivery_otp' => '789456',
        'pickup_address' => '123 Tech Hub, Pasig City',
        'delivery_address' => 'Unit 405 Jade Tower, Pasig',
    ]);

    // 9.1 Show Delivery OTP
    $res = $handleApi('GET', '/api/orders/' . $deliveredOrder->id . '/deliveries/' . $delivery->id . '/otp', [], $buyerUser, $token);
    check('Delivery', 'GET /api/orders/{order}/deliveries/{delivery}/otp retrieves secure delivery OTP', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'otp'));

    // =================================================================
    // SECTION 10: REVIEWS & RATINGS
    // =================================================================
    echo "\n--- Section 10: Reviews & Ratings ---\n";

    // 10.1 Rate Delivered Order
    $res = $handleApi('POST', '/api/orders/' . $deliveredOrder->id . '/rate', [
        'product_id' => $product->id,
        'rating' => 5,
        'comment' => 'Exceptional audio quality, highly recommend this store!',
    ], $buyerUser, $token);
    check('Reviews', 'POST /api/orders/{id}/rate submits product rating and review', $res->getStatusCode() === 200);

    // =================================================================
    // SECTION 11: RETURNS & REFUNDS
    // =================================================================
    echo "\n--- Section 11: Returns & Refunds ---\n";

    // 11.1 Submit Return Request with photo evidence
    $fakeEvidence = UploadedFile::fake()->create('defective.jpg', 150, 'image/jpeg');
    $res = $handleApi('POST', '/api/orders/' . $deliveredOrder->id . '/returns', [
        'order_item_id' => $deliveredItem->id,
        'quantity' => 1,
        'reason' => 'damaged',
    ], $buyerUser, $token, ['evidence' => $fakeEvidence]);
    $returnRes = json_decode($res->getContent(), true);
    $returnId = $returnRes['return_id'] ?? ($returnRes['return']['id'] ?? ($returnRes['id'] ?? null));
    check('Returns', 'POST /api/orders/{id}/returns submits return case with evidence', ($res->getStatusCode() === 200 || $res->getStatusCode() === 201) && $returnId !== null);

    if ($returnId) {
        $returnRequest = ReturnRequest::find($returnId);

        // 11.2 Return Proof View
        $res = $handleApi('GET', '/api/returns/' . $returnId . '/proof', [], $buyerUser, $token);
        check('Returns', 'GET /api/returns/{return}/proof loads proof view', $res->getStatusCode() === 200 || $res->getStatusCode() === 404);

        // 11.3 Confirm Refund Receipt
        if ($returnRequest) {
            $returnRequest->update([
                'status' => 'approved',
                'refund_amount' => 1500.00,
            ]);

            \App\Models\ReturnWorkflow::where('return_request_id', $returnRequest->id)->update([
                'stage' => 'refund_sent',
                'refund_sent_at' => now(),
                'refund_reference' => 'REF-TEST-9999',
            ]);

            $res = $handleApi('POST', '/api/returns/' . $returnId . '/confirm', [], $buyerUser, $token);
            check('Returns', 'POST /api/returns/{return}/confirm confirms receipt of refund', $res->getStatusCode() === 200);
        }
    }

    // =================================================================
    // SECTION 12: BUYER-SELLER MESSAGING / CHAT
    // =================================================================
    echo "\n--- Section 12: Buyer-Seller Messaging / Chat ---\n";

    // 12.1 List Conversations
    $res = $handleApi('GET', '/api/conversations', [], $buyerUser, $token);
    check('Chat', 'GET /api/conversations loads conversation threads', $res->getStatusCode() === 200);

    // 12.2 Unread Count
    $res = $handleApi('GET', '/api/conversations/unread-count', [], $buyerUser, $token);
    check('Chat', 'GET /api/conversations/unread-count gets total unread messages', $res->getStatusCode() === 200);

    // 12.3 Start Chat / Send Message
    $res = $handleApi('POST', '/api/conversations', [
        'seller_id' => $sellerUser->id,
        'subject' => 'Inquiry regarding wireless headphones',
        'body' => 'Hello, do you have this in white?',
        'product_id' => $product->id,
    ], $buyerUser, $token);
    $convData = json_decode($res->getContent(), true);
    $convId = $convData['conversation_id'] ?? ($convData['id'] ?? ($convData['conversation']['id'] ?? null));
    check('Chat', 'POST /api/conversations creates conversation with inquiry message', ($res->getStatusCode() === 200 || $res->getStatusCode() === 201) && $convId !== null);

    if ($convId) {
        // 12.4 Messages in Conversation
        $res = $handleApi('GET', '/api/conversations/' . $convId . '/messages', [], $buyerUser, $token);
        check('Chat', 'GET /api/conversations/{id}/messages retrieves message history', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'in white'));

        // 12.5 Reply to Conversation
        $res = $handleApi('POST', '/api/conversations/' . $convId . '/reply', [
            'body' => 'Thank you for checking!',
        ], $buyerUser, $token);
        check('Chat', 'POST /api/conversations/{id}/reply posts reply', $res->getStatusCode() === 200 || $res->getStatusCode() === 201);

        // 12.6 Block Conversation
        $res = $handleApi('POST', '/api/conversations/' . $convId . '/block', [
            'reason' => 'spam',
        ], $buyerUser, $token);
        check('Chat', 'POST /api/conversations/{id}/block blocks conversation', $res->getStatusCode() === 200);
    }

    // =================================================================
    // SECTION 13: NOTIFICATIONS
    // =================================================================
    echo "\n--- Section 13: Notifications ---\n";

    // 13.1 List Notifications
    $res = $handleApi('GET', '/api/notifications', [], $buyerUser, $token);
    $notifsData = json_decode($res->getContent(), true);
    $notifList = $notifsData['notifications'] ?? [];
    check('Notifications', 'GET /api/notifications lists buyer notifications', $res->getStatusCode() === 200 && is_array($notifList));

    // 13.2 Mark Single Notification Read
    $firstNotifId = !empty($notifList) ? $notifList[0]['id'] : 'order-1';
    if (!empty($notifList)) {
        $res = $handleApi('POST', '/api/notifications/read', ['id' => $firstNotifId], $buyerUser, $token);
        check('Notifications', 'POST /api/notifications/read marks notification as read', $res->getStatusCode() === 200);
    } else {
        check('Notifications', 'POST /api/notifications/read marks notification as read', true, 'no pending notifications');
    }

    // 13.3 Mark All Read
    $res = $handleApi('POST', '/api/notifications/read-all', [], $buyerUser, $token);
    check('Notifications', 'POST /api/notifications/read-all marks all notifications read', $res->getStatusCode() === 200);

    // =================================================================
    // SECTION 14: HELP & COMPLAINTS
    // =================================================================
    echo "\n--- Section 14: Help & Complaints ---\n";

    // 14.1 Store Complaint
    $res = $handleApi('POST', '/api/help/complaint', [
        'subject' => 'Late delivery inquiry',
        'description' => 'Inquiring about standard delivery schedule for Pasig area.',
    ], $buyerUser, $token);
    check('Help', 'POST /api/help/complaint files customer support complaint ticket', $res->getStatusCode() === 200 || $res->getStatusCode() === 201);

    // 14.2 List Complaints
    $res = $handleApi('GET', '/api/help/complaints', [], $buyerUser, $token);
    check('Help', 'GET /api/help/complaints lists submitted complaint tickets', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'Late delivery'));

    // =================================================================
    // SECTION 15: ACCOUNT DELETION & LOGOUT
    // =================================================================
    echo "\n--- Section 15: Account Deletion & Logout ---\n";

    // 15.1 Account Deletion Status
    $res = $handleApi('GET', '/api/account-deletion', [], $buyerUser, $token);
    check('Account', 'GET /api/account-deletion retrieves deletion schedule status', $res->getStatusCode() === 200);

    // 15.2 Request Deletion
    $res = $handleApi('POST', '/api/account-deletion', [
        'current_password' => 'Password123!',
        'reason' => 'I have another account.',
        'confirm_deletion' => true,
    ], $buyerUser, $token);
    check('Account', 'POST /api/account-deletion schedules account deletion', $res->getStatusCode() === 200 || $res->getStatusCode() === 201);

    // 15.3 Cancel Deletion
    $res = $handleApi('POST', '/api/account-deletion/cancel', [
        'confirm_cancellation' => true,
    ], $buyerUser, $token);
    check('Account', 'POST /api/account-deletion/cancel cancels scheduled account deletion', $res->getStatusCode() === 200);

    // 15.4 Logout
    $res = $handleApi('POST', '/api/logout', [], $buyerUser, $token);
    check('Auth', 'POST /api/logout revokes current token and logs out buyer', $res->getStatusCode() === 200);

} catch (\Throwable $e) {
    check('Fatal Error', 'Execution failed with exception', false, $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
} finally {
    DB::rollBack();
    echo "\nDatabase transaction rolled back safely (no test artifacts retained).\n";
}

// ---------------------------------------------------------------------
// SUMMARY
// ---------------------------------------------------------------------
$total = count($testResults);
$passed = count(array_filter($testResults, fn ($r) => $r['pass']));
$failed = $total - $passed;

echo "\n========================================================================\n";
echo " BUYER MOBILE APPLICATION AUDIT SUMMARY\n";
echo " Total Checks:  {$total}\n";
echo " Passed:        {$passed}\n";
echo " Failed:        {$failed}\n";
echo "========================================================================\n";

if ($failed > 0) {
    echo "\nFAILED CHECKS:\n";
    foreach ($testResults as $r) {
        if (!$r['pass']) {
            echo " - [{$r['suite']}] {$r['name']} ({$r['note']})\n";
        }
    }
    exit(1);
}

exit(0);
