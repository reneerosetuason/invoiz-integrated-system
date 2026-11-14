<?php

/**
 * End-to-End Functional Test for ALL Buyer Pages, Buttons & Workflows
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
use App\Models\Address;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$testResults = [];

function check(string $suite, string $name, bool $pass, string $note = '')
{
    global $testResults;
    $testResults[] = ['suite' => $suite, 'name' => $name, 'pass' => $pass, 'note' => $note];
    $status = $pass ? "[PASS]" : "[FAIL]";
    echo "{$status} [{$suite}] {$name}" . ($note ? " ({$note})" : '') . PHP_EOL;
}

echo "====================================================================\n";
echo " INVOIZ: Verifying ALL Buyer Pages, Buttons & Interactive Functions \n";
echo "====================================================================\n\n";

DB::beginTransaction();

try {
    // -------------------------------------------------------------
    // Setup Test Buyer, Seller, Product, Address
    // -------------------------------------------------------------
    $buyer = User::create([
        'name' => 'Buyer Tester',
        'first_name' => 'Buyer',
        'last_name' => 'Tester',
        'email' => 'buyer-verify-' . Str::random(8) . '@invoiz-test.local',
        'phone' => '0917' . random_int(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'role' => 'buyer',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $sellerUser = User::create([
        'name' => 'Store Merchant',
        'first_name' => 'Store',
        'last_name' => 'Merchant',
        'email' => 'merchant-verify-' . Str::random(8) . '@invoiz-test.local',
        'phone' => '0918' . random_int(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'role' => 'seller',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $seller = Seller::create([
        'user_id' => $sellerUser->id,
        'store_name' => 'Prime Electronics',
        'business_name' => 'Prime Electronics Inc',
        'line_of_business' => 'Electronics Retail',
        'status' => 'active',
        'approval_status' => 'approved',
        'address_line' => '45 Tech Blvd',
        'barangay' => 'Barangay 659',
        'city' => 'Manila',
        'province' => 'Metro Manila',
    ]);

    $category = Category::first();
    $categoryId = $category ? $category->id : 1;

    $product = Product::create([
        'seller_id' => $sellerUser->id,
        'category_id' => $categoryId,
        'name' => 'Wireless Noise-Canceling Headphones',
        'description' => 'High quality audio headphones with Bluetooth 5.3',
        'price' => 1500.00,
        'stock' => 25,
        'actual_weight_kg' => 0.45,
        'length_cm' => 20.0,
        'width_cm' => 18.0,
        'height_cm' => 8.0,
        'status' => 'active',
        'on_sale' => 1,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'name' => 'Matte Black',
        'price' => 1500.00,
        'stock' => 15,
        'sku' => 'HP-BLK-01',
    ]);

    $address = Address::create([
        'buyer_id' => $buyer->id,
        'user_id' => $buyer->id,
        'recipient_name' => 'Buyer Tester',
        'phone' => '09171234567',
        'address_line' => '123 Palm Street',
        'barangay' => 'San Antonio',
        'city' => 'Quezon City',
        'province' => 'Metro Manila',
        'postal_code' => '1100',
        'is_default' => 1,
    ]);

    // Setup Session & Auth context for buyer
    $session = $app->make('session')->driver();
    $session->start();
    $session->put('buyer', [
        'id' => $buyer->id,
        'first_name' => $buyer->first_name,
        'last_name' => $buyer->last_name,
        'email' => $buyer->email,
    ]);

    function callRoute($app, $method, $uri, $params = [], $session = null, $user = null, $files = []) {
        if ($user) {
            Auth::login($user);
        } else {
            Auth::logout();
        }
        $req = Request::create($uri, $method, $params, [], $files);
        if ($session) {
            $req->setLaravelSession($session);
        }
        return $app->handle($req);
    }

    // =============================================================
    // 1. STOREFRONT NAVIGATION & BROWSING
    // =============================================================
    echo "--- Section 1: Storefront Browsing & Search ---\n";

    // GET / (Landing)
    $res = callRoute($app, 'GET', '/', [], $session, $buyer);
    check('Storefront', 'GET / (Landing page loads)', $res->getStatusCode() === 200, "HTTP " . $res->getStatusCode());

    // GET /shop (Storefront Catalog)
    $res = callRoute($app, 'GET', '/shop', [], $session, $buyer);
    check('Storefront', 'GET /shop (Catalog loads)', $res->getStatusCode() === 200, "HTTP " . $res->getStatusCode());

    // GET /sale (On Sale Filter)
    $res = callRoute($app, 'GET', '/sale', [], $session, $buyer);
    check('Storefront', 'GET /sale (On-Sale products view)', $res->getStatusCode() === 200, "HTTP " . $res->getStatusCode());

    // GET /shop?search=Prime (Search matching store & product)
    $res = callRoute($app, 'GET', '/shop?search=Prime', [], $session, $buyer);
    check('Storefront', 'GET /shop?search=Prime (Store & Product search)', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'Prime Electronics'));

    // GET /product/{id} (Product Details with swipe gallery & variants)
    $res = callRoute($app, 'GET', '/product/' . $product->id, [], $session, $buyer);
    check('Storefront', 'GET /product/{id} (Product detail page loads with details)', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'Wireless Noise-Canceling Headphones'));

    // GET /store/{id} (Storefront Store Page)
    $res = callRoute($app, 'GET', '/store/' . $seller->id, [], $session, $buyer);
    check('Storefront', 'GET /store/{id} (Merchant Store Profile loads)', $res->getStatusCode() === 200 && str_contains($res->getContent(), 'Prime Electronics'));

    // POST /store/{id}/follow (Follow / Unfollow Store button)
    $res = callRoute($app, 'POST', '/store/' . $seller->id . '/follow', ['_token' => $session->token()], $session, $buyer);
    check('Storefront', 'POST /store/{id}/follow (Follow store toggle button)', in_array($res->getStatusCode(), [200, 302]), "HTTP " . $res->getStatusCode());

    // =============================================================
    // 2. WISHLIST & FAVORITES
    // =============================================================
    echo "\n--- Section 2: Wishlist & Favorites ---\n";

    // POST /wishlist/{product}/toggle (Toggle Wishlist button)
    $res = callRoute($app, 'POST', '/wishlist/' . $product->id . '/toggle', ['_token' => $session->token()], $session, $buyer);
    check('Wishlist', 'POST /wishlist/{id}/toggle (Add/Remove favorite button)', in_array($res->getStatusCode(), [200, 302]), "HTTP " . $res->getStatusCode());

    // GET /wishlist
    $res = callRoute($app, 'GET', '/wishlist', [], $session, $buyer);
    check('Wishlist', 'GET /wishlist (Wishlist view loads items)', $res->getStatusCode() === 200, "HTTP " . $res->getStatusCode());

    // =============================================================
    // 3. VOUCHERS & PROMOTIONS
    // =============================================================
    echo "\n--- Section 3: Vouchers & Promotions ---\n";

    $voucher = Voucher::create([
        'seller_id' => $seller->id,
        'code' => 'PRIME50',
        'name' => 'Prime 50 OFF',
        'type' => 'fixed',
        'discount_type' => 'fixed',
        'value' => 50.00,
        'discount_value' => 50.00,
        'min_spend' => 500.00,
        'usage_limit' => 100,
        'used_count' => 0,
        'status' => 'active',
        'starts_at' => now()->subDay(),
        'expires_at' => now()->addDays(30),
    ]);

    // GET /vouchers
    $res = callRoute($app, 'GET', '/vouchers', [], $session, $buyer);
    check('Vouchers', 'GET /vouchers (Vouchers promo center loads)', $res->getStatusCode() === 200);

    // POST /vouchers/{voucher}/claim (Claim Voucher button)
    $res = callRoute($app, 'POST', '/vouchers/' . $voucher->id . '/claim', ['_token' => $session->token()], $session, $buyer);
    check('Vouchers', 'POST /vouchers/{id}/claim (Claim voucher button works)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 4. CART OPERATIONS
    // =============================================================
    echo "\n--- Section 4: Shopping Cart Operations ---\n";

    // POST /cart/add/{id} (Add to cart button)
    $addParams = [
        '_token' => $session->token(),
        'quantity' => 2,
        'variant_id' => $variant->id,
    ];
    $res = callRoute($app, 'POST', '/cart/add/' . $product->id, $addParams, $session, $buyer);
    check('Cart', 'POST /cart/add/{id} (Add to Cart button)', in_array($res->getStatusCode(), [200, 302]));

    // GET /cart
    $res = callRoute($app, 'GET', '/cart', [], $session, $buyer);
    check('Cart', 'GET /cart (Shopping cart page loads with items)', $res->getStatusCode() === 200);

    $cartItem = Cart::itemsFor($buyer->id)->first();
    $itemId = $cartItem ? $cartItem->id : 0;

    // POST /cart/inc/{item} (+ button)
    $res = callRoute($app, 'POST', '/cart/inc/' . $itemId, ['_token' => $session->token()], $session, $buyer);
    check('Cart', 'POST /cart/inc/{item} (Increase quantity + button)', in_array($res->getStatusCode(), [200, 302]));

    // POST /cart/dec/{item} (- button)
    $res = callRoute($app, 'POST', '/cart/dec/' . $itemId, ['_token' => $session->token()], $session, $buyer);
    check('Cart', 'POST /cart/dec/{item} (Decrease quantity - button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 5. CHECKOUT & ORDER PLACEMENT
    // =============================================================
    echo "\n--- Section 5: Checkout & Order Placement ---\n";

    // GET /checkout (Checkout summary page)
    $res = callRoute($app, 'GET', '/checkout?all=1', [], $session, $buyer);
    check('Checkout', 'GET /checkout (Checkout review page loads)', $res->getStatusCode() === 200);

    // POST /checkout (Place Order button - Cash on Delivery)
    $checkoutParams = [
        '_token' => $session->token(),
        'address_id' => $address->id,
        'payment_method' => 'cod',
        'checkout_mode' => 'all',
    ];
    $res = callRoute($app, 'POST', '/checkout', $checkoutParams, $session, $buyer);
    
    if ($session->has('errors')) {
        echo "Checkout errors: " . implode(', ', $session->get('errors')->all()) . "\n";
    }
    if ($session->has('error')) {
        echo "Checkout flash error: " . $session->get('error') . "\n";
    }

    $placedOrder = Order::where('buyer_id', $buyer->id)->latest('id')->first();
    check('Checkout', 'POST /checkout (Place Order button creates order)', $placedOrder !== null, "Order #" . ($placedOrder->id ?? 'none'));

    // =============================================================
    // 6. ORDER TRACKING, CANCELLATION & REVIEWS
    // =============================================================
    echo "\n--- Section 6: Orders Management & Details ---\n";

    // GET /orders
    $res = callRoute($app, 'GET', '/orders', [], $session, $buyer);
    check('Orders', 'GET /orders (My Orders list loads)', $res->getStatusCode() === 200);

    if ($placedOrder) {
        // GET /orders/{id}
        $res = callRoute($app, 'GET', '/orders/' . $placedOrder->id, [], $session, $buyer);
        check('Orders', 'GET /orders/{id} (Order Detail view loads)', $res->getStatusCode() === 200);

        // POST /orders/{id}/cancel (Cancel Order button)
        $cancelParams = [
            '_token' => $session->token(),
            'reason' => 'changed_mind',
        ];
        $res = callRoute($app, 'POST', '/orders/' . $placedOrder->id . '/cancel', $cancelParams, $session, $buyer);
        check('Orders', 'POST /orders/{id}/cancel (Cancel order button works)', in_array($res->getStatusCode(), [200, 302]));
        
        $placedOrder->refresh();
        check('Orders', 'Order status updated to cancelled', $placedOrder->status === 'cancelled');
    }

    // Create a delivered order to test Rating & Return submission
    $deliveredOrder = Order::create([
        'buyer_id' => $buyer->id,
        'status' => 'delivered',
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'total' => 1500.00,
        'address_id' => $address->id,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $deliveredOrder->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'seller_id' => $sellerUser->id,
        'price' => 1500.00,
        'quantity' => 1,
    ]);

    // POST /orders/{order}/rate/{product} (Rate & Review button)
    $rateParams = [
        '_token' => $session->token(),
        'rating' => 5,
        'review' => 'Excellent noise canceling headphones! Very comfortable.',
    ];
    $res = callRoute($app, 'POST', '/orders/' . $deliveredOrder->id . '/rate/' . $product->id, $rateParams, $session, $buyer);
    check('Orders', 'POST /orders/{id}/rate/{product} (Submit product review button)', in_array($res->getStatusCode(), [200, 302]));

    // POST /orders/{id}/returns (Request Return / Refund button with evidence)
    $evidenceFile = UploadedFile::fake()->create('defect.jpg', 100, 'image/jpeg');
    $returnParams = [
        '_token' => $session->token(),
        'order_item_id' => $orderItem->id,
        'quantity' => 1,
        'reason' => 'damaged',
        'reason_details' => 'The left speaker is not working properly upon unboxing.',
    ];
    $res = callRoute($app, 'POST', '/orders/' . $deliveredOrder->id . '/returns', $returnParams, $session, $buyer, ['evidence' => $evidenceFile]);
    check('Orders', 'POST /orders/{id}/returns (Submit return/refund request button with photo evidence)', in_array($res->getStatusCode(), [200, 302]));

    $returnReq = ReturnRequest::where('order_id', $deliveredOrder->id)->first();
    check('Orders', 'Return request recorded in database', $returnReq !== null, "Return #" . ($returnReq->id ?? 'none'));

    // =============================================================
    // 7. BUYER PROFILE & ADDRESS MANAGEMENT
    // =============================================================
    echo "\n--- Section 7: Buyer Profile & Delivery Addresses ---\n";

    // GET /profile
    $res = callRoute($app, 'GET', '/profile', [], $session, $buyer);
    check('Profile', 'GET /profile (Profile settings page loads)', $res->getStatusCode() === 200);

    // POST /profile (Update personal information button)
    $profileUpdate = [
        '_token' => $session->token(),
        'first_name' => 'Buyer',
        'last_name' => 'Updated',
        'phone' => '0917' . random_int(1000000, 9999999),
    ];
    $res = callRoute($app, 'POST', '/profile', $profileUpdate, $session, $buyer);
    check('Profile', 'POST /profile (Update profile button saves changes)', in_array($res->getStatusCode(), [200, 302]));

    // POST /profile/addresses (Add new address button)
    $newAddressData = [
        '_token' => $session->token(),
        'recipient_name' => 'Buyer Tester Office',
        'phone' => '09181234567',
        'address_line' => '789 Business Park',
        'barangay' => 'Fort Bonifacio',
        'city' => 'Taguig',
        'province' => 'Metro Manila',
        'postal_code' => '1630',
    ];
    $res = callRoute($app, 'POST', '/profile/addresses', $newAddressData, $session, $buyer);
    check('Profile', 'POST /profile/addresses (Add new delivery address button)', in_array($res->getStatusCode(), [200, 302]));

    $createdAddr = Address::where('recipient_name', 'Buyer Tester Office')->first();
    if ($createdAddr) {
        // POST /profile/addresses/{id}/default (Set as default button)
        $res = callRoute($app, 'POST', '/profile/addresses/' . $createdAddr->id . '/default', ['_token' => $session->token()], $session, $buyer);
        check('Profile', 'POST /profile/addresses/{id}/default (Set default address button)', in_array($res->getStatusCode(), [200, 302]));

        // DELETE /profile/addresses/{id} (Delete address button)
        $res = callRoute($app, 'DELETE', '/profile/addresses/' . $createdAddr->id, ['_token' => $session->token()], $session, $buyer);
        check('Profile', 'DELETE /profile/addresses/{id} (Delete address button)', in_array($res->getStatusCode(), [200, 302]));
    }

    // =============================================================
    // 8. BUYER-SELLER MESSAGING & CHAT
    // =============================================================
    echo "\n--- Section 8: Buyer-Seller Real-Time Chat ---\n";

    // GET /messages
    $res = callRoute($app, 'GET', '/messages', [], $session, $buyer);
    check('Messages', 'GET /messages (Inbox conversations list loads)', $res->getStatusCode() === 200);

    // GET /chat/seller/{sellerId}
    $res = callRoute($app, 'GET', '/chat/seller/' . $sellerUser->id, [], $session, $buyer);
    check('Messages', 'GET /chat/seller/{id} (Chat thread loads with merchant)', $res->getStatusCode() === 200);

    // POST /chat/send/{sellerId} (Send Message button)
    $chatData = [
        '_token' => $session->token(),
        'body' => 'Hello seller, is this headphones available in white?',
    ];
    $res = callRoute($app, 'POST', '/chat/send/' . $sellerUser->id, $chatData, $session, $buyer);
    check('Messages', 'POST /chat/send/{sellerId} (Send chat message button works)', in_array($res->getStatusCode(), [200, 201]));

    // GET /chat/fetch/{sellerId} (Live polling endpoint)
    $res = callRoute($app, 'GET', '/chat/fetch/' . $sellerUser->id, [], $session, $buyer);
    check('Messages', 'GET /chat/fetch/{sellerId} (Live chat message polling)', $res->getStatusCode() === 200);

    // =============================================================
    // 9. NOTIFICATIONS
    // =============================================================
    echo "\n--- Section 9: Notifications ---\n";

    // GET /notifications
    $res = callRoute($app, 'GET', '/notifications', [], $session, $buyer);
    check('Notifications', 'GET /notifications (Notification center loads)', $res->getStatusCode() === 200);

    // POST /notifications/read-all (Mark all as read button)
    $res = callRoute($app, 'POST', '/notifications/read-all', ['_token' => $session->token()], $session, $buyer);
    check('Notifications', 'POST /notifications/read-all (Mark all as read button works)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 10. HELP & COMPLAINT FILING
    // =============================================================
    echo "\n--- Section 10: Help Center & Complaints ---\n";

    // GET /help
    $res = callRoute($app, 'GET', '/help', [], $session, $buyer);
    check('Help', 'GET /help (Help & support center loads)', $res->getStatusCode() === 200);

    // POST /help/complaint (Submit complaint form)
    $complaintData = [
        '_token' => $session->token(),
        'order_id' => $deliveredOrder->id,
        'reason' => 'delayed_delivery',
        'details' => 'Delivery rider took longer than the estimated delivery window.',
    ];
    $res = callRoute($app, 'POST', '/help/complaint', $complaintData, $session, $buyer);
    check('Help', 'POST /help/complaint (Submit support complaint button works)', in_array($res->getStatusCode(), [200, 302]));

} finally {
    // Rollback test records cleanly
    DB::rollBack();
    echo "\n[INFO] Database transaction rolled back cleanly; zero test data persisted.\n";
}

echo "\n====================================================================\n";
$total = count($testResults);
$passed = count(array_filter($testResults, fn($r) => $r['pass']));
$failed = $total - $passed;
echo "BUYER FUNCTIONS AUDIT SUMMARY: Total {$total} checks: {$passed} Passed, {$failed} Failed.\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
