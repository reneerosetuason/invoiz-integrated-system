<?php

/**
 * End-to-End Functional Test for ALL Seller Center Pages, Buttons & Workflows
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
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Address;
use App\Models\Voucher;
use App\Models\Sale;
use App\Models\ProductSale;
use App\Models\Review;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\SellerNotification;
use App\Models\ReturnRequest;
use App\Models\Delivery;
use App\Models\PayoutAccount;
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
echo " INVOIZ: Verifying ALL Seller Pages, Buttons & Interactive Functions\n";
echo "====================================================================\n\n";

DB::beginTransaction();

try {
    // -------------------------------------------------------------
    // Setup Test Seller, Store, Buyer, Product, Category
    // -------------------------------------------------------------
    $sellerUser = User::create([
        'name' => 'Seller Master',
        'first_name' => 'Seller',
        'last_name' => 'Master',
        'email' => 'seller-test-' . Str::random(8) . '@invoiz-test.local',
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
        'store_name' => 'Apex Retail Store',
        'business_name' => 'Apex Retail Enterprises Inc',
        'line_of_business' => 'Consumer Goods',
        'status' => 'active',
        'approval_status' => 'approved',
        'address_line' => '88 Commercial Hub',
        'barangay' => 'Barangay 100',
        'city' => 'Manila',
        'province' => 'Metro Manila',
        'vacation_mode' => false,
    ]);

    $buyerUser = User::create([
        'name' => 'Customer Tester',
        'first_name' => 'Customer',
        'last_name' => 'Tester',
        'email' => 'cust-test-' . Str::random(8) . '@invoiz-test.local',
        'phone' => '0918' . random_int(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'role' => 'buyer',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $category = Category::first();
    $categoryId = $category ? $category->id : 1;

    $product = Product::create([
        'seller_id' => $sellerUser->id,
        'category_id' => $categoryId,
        'name' => 'Smart LED Desk Lamp',
        'description' => 'Dimmable smart lamp with wireless charging base',
        'price' => 850.00,
        'stock' => 50,
        'actual_weight_kg' => 0.85,
        'length_cm' => 25.0,
        'width_cm' => 15.0,
        'height_cm' => 35.0,
        'status' => 'active',
        'on_sale' => 0,
    ]);

    // Setup Session for Seller
    $session = $app->make('session')->driver();
    $session->start();
    $session->put('active_role', 'seller');
    $session->put('seller_id', $seller->id);

    function callSeller($app, $method, $uri, $params = [], $session = null, $user = null, $files = []) {
        if ($user) {
            Auth::login($user);
        }
        $req = Request::create($uri, $method, $params, [], $files);
        if ($session) {
            $req->setLaravelSession($session);
        }
        return $app->handle($req);
    }

    // =============================================================
    // 1. DASHBOARD & REPORTS
    // =============================================================
    echo "--- Section 1: Dashboard & Reports ---\n";

    // GET /seller/dashboard
    $res = callSeller($app, 'GET', '/seller/dashboard', [], $session, $sellerUser);
    check('Dashboard', 'GET /seller/dashboard (Dashboard view loads)', $res->getStatusCode() === 200, "HTTP " . $res->getStatusCode());

    // GET /seller/reports
    $res = callSeller($app, 'GET', '/seller/reports', [], $session, $sellerUser);
    check('Dashboard', 'GET /seller/reports (Reports and Analytics load)', $res->getStatusCode() === 200, "HTTP " . $res->getStatusCode());

    // =============================================================
    // 2. PRODUCT MANAGEMENT (CRUD, DIMENSIONS & ARCHIVING)
    // =============================================================
    echo "\n--- Section 2: Product Management & Volumetric Dimensions ---\n";

    // GET /seller/products (List products)
    $res = callSeller($app, 'GET', '/seller/products', [], $session, $sellerUser);
    check('Products', 'GET /seller/products (Catalog index loads)', $res->getStatusCode() === 200);

    // GET /seller/products/create (Product creation form)
    $res = callSeller($app, 'GET', '/seller/products/create', [], $session, $sellerUser);
    check('Products', 'GET /seller/products/create (Creation form with weight/dimensions loads)', $res->getStatusCode() === 200);

    // POST /seller/products (Add New Product with kg and cm dimensions)
    $newProductParams = [
        '_token' => $session->token(),
        'name' => 'Compact Bluetooth Speaker',
        'category_id' => $categoryId,
        'description' => 'Waterproof portable bluetooth speaker with 12h battery',
        'price' => 1200.00,
        'stock' => 30,
        'weight' => '0.60',
        'length' => 18.0,
        'width' => 10.0,
        'height' => 10.0,
        'status' => 'active',
    ];
    $res = callSeller($app, 'POST', '/seller/products', $newProductParams, $session, $sellerUser);
    check('Products', 'POST /seller/products (Save product button)', in_array($res->getStatusCode(), [200, 302]), "HTTP " . $res->getStatusCode());

    $createdProduct = Product::where('name', 'Compact Bluetooth Speaker')->first();
    check('Products', 'New product created with dimensions', $createdProduct !== null && (float)$createdProduct->weight_value == 0.60 && (float)$createdProduct->length == 18.0 && (float)$createdProduct->width == 10.0 && (float)$createdProduct->height == 10.0);

    $targetProductId = $createdProduct ? $createdProduct->id : $product->id;

    // GET /seller/products/{id}/edit (Edit form)
    $res = callSeller($app, 'GET', '/seller/products/' . $targetProductId . '/edit', [], $session, $sellerUser);
    check('Products', 'GET /seller/products/{id}/edit (Edit product form loads)', $res->getStatusCode() === 200);

    // POST /seller/products/{id} (Update Product)
    $updateProductParams = [
        '_token' => $session->token(),
        'name' => 'Compact Bluetooth Speaker Pro',
        'category_id' => $categoryId,
        'description' => 'Upgraded waterproof bluetooth speaker with 15h battery',
        'price' => 1350.00,
        'stock' => 28,
        'weight' => '0.65',
        'length' => 18.5,
        'width' => 10.5,
        'height' => 10.5,
        'status' => 'active',
    ];
    $res = callSeller($app, 'POST', '/seller/products/' . $targetProductId, $updateProductParams, $session, $sellerUser);
    check('Products', 'POST /seller/products/{id} (Update product button)', in_array($res->getStatusCode(), [200, 302]));

    // POST /seller/products/{id}/archive (Archive Product button)
    $res = callSeller($app, 'POST', '/seller/products/' . $targetProductId . '/archive', ['_token' => $session->token()], $session, $sellerUser);
    check('Products', 'POST /seller/products/{id}/archive (Archive product button)', in_array($res->getStatusCode(), [200, 302]));

    // POST /seller/products/{id}/restore (Restore Product button)
    $res = callSeller($app, 'POST', '/seller/products/' . $targetProductId . '/restore', ['_token' => $session->token()], $session, $sellerUser);
    check('Products', 'POST /seller/products/{id}/restore (Restore product button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 3. ORDER FULFILLMENT & SHIPPING
    // =============================================================
    echo "\n--- Section 3: Order Fulfillment & Waybill Generation ---\n";

    // Setup an order for this seller
    $address = Address::create([
        'buyer_id' => $buyerUser->id,
        'user_id' => $buyerUser->id,
        'recipient_name' => 'Customer Tester',
        'phone' => '09181112222',
        'address_line' => '55 Maple Drive',
        'barangay' => 'Barangay 1',
        'city' => 'Manila',
        'province' => 'Metro Manila',
        'postal_code' => '1000',
        'is_default' => 1,
    ]);

    $order = Order::create([
        'buyer_id' => $buyerUser->id,
        'status' => 'pending',
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'total' => 850.00,
        'address_id' => $address->id,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'seller_id' => $sellerUser->id,
        'price' => 850.00,
        'quantity' => 1,
    ]);

    // GET /seller/orders (Orders list)
    $res = callSeller($app, 'GET', '/seller/orders', [], $session, $sellerUser);
    check('Orders', 'GET /seller/orders (Orders list loads)', $res->getStatusCode() === 200);

    // GET /seller/orders/{id} (Order Detail)
    $res = callSeller($app, 'GET', '/seller/orders/' . $order->id, [], $session, $sellerUser);
    check('Orders', 'GET /seller/orders/{id} (Order details view loads)', $res->getStatusCode() === 200);

    // POST /seller/orders/{id}/status (Update Order Status to processing / ready)
    $statusParams = [
        '_token' => $session->token(),
        'status' => 'processing',
    ];
    $res = callSeller($app, 'POST', '/seller/orders/' . $order->id . '/status', $statusParams, $session, $sellerUser);
    check('Orders', 'POST /seller/orders/{id}/status (Update order status button)', in_array($res->getStatusCode(), [200, 302]));

    // GET /seller/orders/{id}/waybill (Print Waybill button)
    $res = callSeller($app, 'GET', '/seller/orders/' . $order->id . '/waybill', [], $session, $sellerUser);
    check('Orders', 'GET /seller/orders/{id}/waybill (Print waybill layout loads)', $res->getStatusCode() === 200);

    // =============================================================
    // 4. RETURNS & REFUNDS RESOLUTION
    // =============================================================
    echo "\n--- Section 4: Returns & Refunds Resolution ---\n";

    // Create a return request for this seller's item
    $returnRequest = ReturnRequest::create([
        'order_id' => $order->id,
        'order_item_id' => $orderItem->id,
        'buyer_id' => $buyerUser->id,
        'seller_id' => $sellerUser->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'reason' => 'damaged',
        'reason_details' => 'Lamp power cord does not turn on.',
        'refund_amount' => 850.00,
        'status' => 'requested',
    ]);

    // GET /seller/returns (Returns list)
    $res = callSeller($app, 'GET', '/seller/returns', [], $session, $sellerUser);
    check('Returns', 'GET /seller/returns (Return requests list loads)', $res->getStatusCode() === 200);

    // POST /seller/returns/{id} (Approve return / resolution action button)
    $returnActionParams = [
        '_token' => $session->token(),
        'action' => 'accept',
        'response_note' => 'Please ship back the item in its original box.',
    ];
    $res = callSeller($app, 'POST', '/seller/returns/' . $returnRequest->id, $returnActionParams, $session, $sellerUser);
    check('Returns', 'POST /seller/returns/{id} (Seller review & resolve return button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 5. VOUCHERS MANAGEMENT
    // =============================================================
    echo "\n--- Section 5: Store Vouchers Management ---\n";

    // GET /seller/vouchers
    $res = callSeller($app, 'GET', '/seller/vouchers', [], $session, $sellerUser);
    check('Vouchers', 'GET /seller/vouchers (Vouchers management page loads)', $res->getStatusCode() === 200);

    // POST /seller/vouchers (Create Voucher button)
    $newVoucherParams = [
        '_token' => $session->token(),
        'code' => 'APEX100',
        'name' => '100 OFF Store Promo',
        'discount_type' => 'fixed',
        'discount_value' => 100.00,
        'min_spend' => 1000.00,
        'usage_limit' => 50,
        'valid_from' => now()->toDateString(),
        'valid_until' => now()->addDays(15)->toDateString(),
        'status' => 'active',
    ];
    $res = callSeller($app, 'POST', '/seller/vouchers', $newVoucherParams, $session, $sellerUser);
    check('Vouchers', 'POST /seller/vouchers (Create store voucher button)', in_array($res->getStatusCode(), [200, 302]), "HTTP " . $res->getStatusCode());

    $sellerVoucher = Voucher::where('code', 'APEX100')->first();
    check('Vouchers', 'Voucher saved in database', $sellerVoucher !== null);

    if ($sellerVoucher) {
        // POST /seller/vouchers/{id}/status (Toggle Voucher Status button)
        $res = callSeller($app, 'POST', '/seller/vouchers/' . $sellerVoucher->id . '/status', ['_token' => $session->token()], $session, $sellerUser);
        check('Vouchers', 'POST /seller/vouchers/{id}/status (Toggle voucher active status button)', in_array($res->getStatusCode(), [200, 302]));

        // DELETE /seller/vouchers/{id} (Delete Voucher button)
        $res = callSeller($app, 'DELETE', '/seller/vouchers/' . $sellerVoucher->id, ['_token' => $session->token()], $session, $sellerUser);
        check('Vouchers', 'DELETE /seller/vouchers/{id} (Delete voucher button)', in_array($res->getStatusCode(), [200, 302]));
    }

    // =============================================================
    // 6. FLASH SALES & CAMPAIGNS
    // =============================================================
    echo "\n--- Section 6: Flash Sales & Campaigns ---\n";

    // GET /seller/sales
    $res = callSeller($app, 'GET', '/seller/sales', [], $session, $sellerUser);
    check('Sales', 'GET /seller/sales (Promotional campaigns view loads)', $res->getStatusCode() === 200);

    // POST /seller/sales (Create Sale Campaign button)
    $saleParams = [
        '_token' => $session->token(),
        'name' => 'Payday Flash Sale',
        'start_time' => now()->addDay()->format('Y-m-d H:i:s'),
        'end_time' => now()->addDays(3)->format('Y-m-d H:i:s'),
    ];
    $res = callSeller($app, 'POST', '/seller/sales', $saleParams, $session, $sellerUser);
    check('Sales', 'POST /seller/sales (Create sale campaign button)', in_array($res->getStatusCode(), [200, 302]));

    $createdSale = Sale::where('seller_id', $sellerUser->id)->latest('id')->first();
    if ($createdSale) {
        // DELETE /seller/sales/{id} (Delete Sale button)
        $res = callSeller($app, 'DELETE', '/seller/sales/' . $createdSale->id, ['_token' => $session->token()], $session, $sellerUser);
        check('Sales', 'DELETE /seller/sales/{id} (Delete sale campaign button)', in_array($res->getStatusCode(), [200, 302]));
    }

    // =============================================================
    // 7. PAYOUTS & REMITTANCES
    // =============================================================
    echo "\n--- Section 7: Payouts & Financial Remittances ---\n";

    // GET /seller/payouts — page was removed; the route must return 404
    $res = callSeller($app, 'GET', '/seller/payouts', [], $session, $sellerUser);
    check('Financials', 'GET /seller/payouts (removed: returns 404)', $res->getStatusCode() === 404);

    // GET /seller/remittances
    $res = callSeller($app, 'GET', '/seller/remittances', [], $session, $sellerUser);
    check('Financials', 'GET /seller/remittances (Logistics remittances ledger loads)', $res->getStatusCode() === 200);

    // =============================================================
    // 8. CUSTOMER REVIEWS & FEEDBACK
    // =============================================================
    echo "\n--- Section 8: Reviews & Customer Feedback ---\n";

    $review = Review::create([
        'buyer_id' => $buyerUser->id,
        'user_id' => $buyerUser->id,
        'product_id' => $product->id,
        'order_id' => $order->id,
        'rating' => 5,
        'comment' => 'The desk lamp is fantastic and very bright!',
        'status' => 'visible',
    ]);

    // GET /seller/feedback
    $res = callSeller($app, 'GET', '/seller/feedback', [], $session, $sellerUser);
    check('Feedback', 'GET /seller/feedback (Customer reviews view loads)', $res->getStatusCode() === 200);

    // POST /seller/feedback/{id}/reply (Reply to Review button)
    $replyParams = [
        '_token' => $session->token(),
        'reply' => 'Thank you for choosing Apex Retail! Enjoy your new lamp.',
    ];
    $res = callSeller($app, 'POST', '/seller/feedback/' . $review->id . '/reply', $replyParams, $session, $sellerUser);
    check('Feedback', 'POST /seller/feedback/{id}/reply (Reply to customer review button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 9. CHAT & MESSAGING
    // =============================================================
    echo "\n--- Section 9: Seller Messaging & Chat ---\n";

    $conversation = Conversation::firstOrCreate([
        'buyer_id' => $buyerUser->id,
        'seller_id' => $sellerUser->id,
    ]);

    // GET /seller/chat
    $res = callSeller($app, 'GET', '/seller/chat', [], $session, $sellerUser);
    check('Chat', 'GET /seller/chat (Seller inbox loads)', $res->getStatusCode() === 200);

    // GET /seller/chat/{conversation}
    $res = callSeller($app, 'GET', '/seller/chat/' . $conversation->id, [], $session, $sellerUser);
    check('Chat', 'GET /seller/chat/{id} (Active chat thread with buyer loads)', $res->getStatusCode() === 200);

    // POST /seller/chat/{conversation}/reply (Send Reply button)
    $chatReplyParams = [
        '_token' => $session->token(),
        'body' => 'Hello! Yes, all our items come with a 1-year warranty.',
    ];
    $res = callSeller($app, 'POST', '/seller/chat/' . $conversation->id . '/reply', $chatReplyParams, $session, $sellerUser);
    check('Chat', 'POST /seller/chat/{id}/reply (Send reply message button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 10. NOTIFICATIONS
    // =============================================================
    echo "\n--- Section 10: Merchant Notifications ---\n";

    SellerNotification::create([
        'seller_id' => $sellerUser->id,
        'order_id' => $order->id,
        'product_id' => $product->id,
        'type' => 'new_order',
        'title' => 'New Order Received',
        'body' => 'Order #' . $order->id . ' has been paid and is waiting for fulfillment.',
        'is_read' => false,
    ]);

    // GET /seller/notifications
    $res = callSeller($app, 'GET', '/seller/notifications', [], $session, $sellerUser);
    check('Notifications', 'GET /seller/notifications (Notifications feed loads)', $res->getStatusCode() === 200);

    // POST /seller/notifications/read-all (Mark all read button)
    $res = callSeller($app, 'POST', '/seller/notifications/read-all', ['_token' => $session->token()], $session, $sellerUser);
    check('Notifications', 'POST /seller/notifications/read-all (Mark all read button works)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 11. STORE SETTINGS & PROFILE
    // =============================================================
    echo "\n--- Section 11: Store Settings & Profile Management ---\n";

    // GET /seller/account
    $res = callSeller($app, 'GET', '/seller/account', [], $session, $sellerUser);
    check('Settings', 'GET /seller/account (Account & Store settings page loads)', $res->getStatusCode() === 200);

    // POST /seller/account/profile (Update Store Details button)
    $updateProfileParams = [
        '_token' => $session->token(),
        'store_name' => 'Apex Retail Global',
        'description' => 'Top-rated consumer electronics and lifestyle store',
        'phone' => '0917' . random_int(1000000, 9999999),
    ];
    $res = callSeller($app, 'POST', '/seller/account/profile', $updateProfileParams, $session, $sellerUser);
    check('Settings', 'POST /seller/account/profile (Update store profile button)', in_array($res->getStatusCode(), [200, 302]));

    // POST /seller/account/pickup-address (Update Pickup Address button)
    $updateAddressParams = [
        '_token' => $session->token(),
        'pickup_address' => '99 Dispatch Center Road',
        'barangay' => 'Barangay 200',
        'city' => 'Manila',
        'province' => 'Metro Manila',
    ];
    $res = callSeller($app, 'POST', '/seller/account/pickup-address', $updateAddressParams, $session, $sellerUser);
    check('Settings', 'POST /seller/account/pickup-address (Update warehouse pickup address button)', in_array($res->getStatusCode(), [200, 302]));

    // POST /seller/account/vacation (Toggle Vacation Mode button)
    $vacationParams = [
        '_token' => $session->token(),
        'vacation_mode' => '1',
    ];
    $res = callSeller($app, 'POST', '/seller/account/vacation', $vacationParams, $session, $sellerUser);
    check('Settings', 'POST /seller/account/vacation (Toggle vacation mode button)', in_array($res->getStatusCode(), [200, 302]));

} finally {
    // Rollback test records cleanly
    DB::rollBack();
    echo "\n[INFO] Database transaction rolled back cleanly; zero test data persisted.\n";
}

echo "\n====================================================================\n";
$total = count($testResults);
$passed = count(array_filter($testResults, fn($r) => $r['pass']));
$failed = $total - $passed;
echo "SELLER FUNCTIONS AUDIT SUMMARY: Total {$total} checks: {$passed} Passed, {$failed} Failed.\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
