<?php

/**
 * End-to-End Functional Test for ALL Admin Portal Pages, Buttons & Workflows
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Seller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Address;
use App\Models\Complaint;
use App\Models\CommissionRate;
use App\Models\PlatformPolicy;
use App\Models\Setting;
use App\Models\Announcement;
use App\Models\AccountDeletionRequest;
use App\Models\WithdrawalRequest;
use App\Models\Payment;
use Illuminate\Http\Request;
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
echo " INVOIZ: Verifying ALL Admin Pages, Buttons & Interactive Functions \n";
echo "====================================================================\n\n";

DB::beginTransaction();

try {
    // -------------------------------------------------------------
    // Setup Test Admin, Seller, Buyer, Category, Product, Order
    // -------------------------------------------------------------
    $admin = User::create([
        'name' => 'Admin Controller',
        'first_name' => 'Admin',
        'last_name' => 'Controller',
        'email' => 'admin-test-' . Str::random(8) . '@invoiz-test.local',
        'phone' => '0917' . random_int(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'role' => 'admin',
        'is_admin' => true,
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $pendingBuyer = User::create([
        'name' => 'Pending Applicant',
        'first_name' => 'Pending',
        'last_name' => 'Applicant',
        'email' => 'pending-buyer-' . Str::random(8) . '@invoiz-test.local',
        'phone' => '0918' . random_int(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'role' => 'buyer',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'pending',
        'email_verified_at' => now(),
    ]);

    $sellerUser = User::create([
        'name' => 'Merchant Candidate',
        'first_name' => 'Merchant',
        'last_name' => 'Candidate',
        'email' => 'merchant-cand-' . Str::random(8) . '@invoiz-test.local',
        'phone' => '0919' . random_int(1000000, 9999999),
        'password' => Hash::make('Password123!'),
        'role' => 'seller',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $seller = Seller::create([
        'user_id' => $sellerUser->id,
        'store_name' => 'Candidate Store PH',
        'business_name' => 'Candidate Store Enterprises',
        'line_of_business' => 'Retail',
        'status' => 'active',
        'approval_status' => 'pending',
        'address_line' => '101 Trade Ave',
        'barangay' => 'Barangay 1',
        'city' => 'Manila',
        'province' => 'Metro Manila',
    ]);

    $category = Category::first();
    if (! $category) {
        $category = Category::create([
            'name' => 'Home & Living',
            'slug' => 'home-living-' . Str::random(5),
            'status' => 'active',
        ]);
    }

    $product = Product::create([
        'seller_id' => $sellerUser->id,
        'category_id' => $category->id,
        'name' => 'Stainless Steel Thermos Flask',
        'description' => 'Vacuum insulated stainless steel water bottle 1L',
        'price' => 450.00,
        'stock' => 100,
        'actual_weight_kg' => 0.50,
        'length_cm' => 10.0,
        'width_cm' => 10.0,
        'height_cm' => 30.0,
        'status' => 'active',
    ]);

    $address = Address::create([
        'buyer_id' => $pendingBuyer->id,
        'user_id' => $pendingBuyer->id,
        'recipient_name' => 'Pending Applicant',
        'phone' => '09181234567',
        'address_line' => '22 Pine Road',
        'barangay' => 'Barangay 2',
        'city' => 'Manila',
        'province' => 'Metro Manila',
        'postal_code' => '1000',
        'is_default' => 1,
    ]);

    $order = Order::create([
        'buyer_id' => $pendingBuyer->id,
        'status' => 'pending',
        'payment_status' => 'paid',
        'payment_method' => 'cod',
        'total' => 450.00,
        'address_id' => $address->id,
    ]);

    $orderItem = OrderItem::create([
        'order_id' => $order->id,
        'product_id' => $product->id,
        'product_name' => $product->name,
        'seller_id' => $sellerUser->id,
        'price' => 450.00,
        'quantity' => 1,
    ]);

    // Setup Admin Web Session
    $session = $app->make('session')->driver();
    $session->start();

    function callAdmin($app, $method, $uri, $params = [], $session = null, $user = null) {
        if ($user) {
            Auth::login($user);
        }
        $req = Request::create($uri, $method, $params);
        if ($session) {
            $req->setLaravelSession($session);
        }
        return $app->handle($req);
    }

    // =============================================================
    // 1. DASHBOARD OVERVIEW
    // =============================================================
    echo "--- Section 1: Dashboard Overview ---\n";

    // GET /admin/dashboard
    $res = callAdmin($app, 'GET', '/admin/dashboard', [], $session, $admin);
    check('Dashboard', 'GET /admin/dashboard (Dashboard view loads)', $res->getStatusCode() === 200, "HTTP " . $res->getStatusCode());

    // =============================================================
    // 2. USER & BUYER MANAGEMENT
    // =============================================================
    echo "\n--- Section 2: User & Buyer Management ---\n";

    // GET /admin/buyers
    $res = callAdmin($app, 'GET', '/admin/buyers', [], $session, $admin);
    check('Users', 'GET /admin/buyers (Buyers list loads)', $res->getStatusCode() === 200);

    // POST /admin/buyers/{user}/review (Approve Buyer Registration button)
    $reviewParams = [
        '_token' => $session->token(),
        'action' => 'approve',
    ];
    $res = callAdmin($app, 'POST', '/admin/buyers/' . $pendingBuyer->id . '/review', $reviewParams, $session, $admin);
    check('Users', 'POST /admin/buyers/{id}/review (Approve buyer button)', in_array($res->getStatusCode(), [200, 302]));

    $pendingBuyer->refresh();
    check('Users', 'Buyer approval_status updated to approved', $pendingBuyer->approval_status === 'approved');

    // GET /admin/manage-accounts
    $res = callAdmin($app, 'GET', '/admin/manage-accounts', [], $session, $admin);
    check('Users', 'GET /admin/manage-accounts (Manage all accounts loads)', $res->getStatusCode() === 200);

    // POST /admin/manage-accounts/{user}/action (Suspend/Activate Account toggle button)
    $actionParams = [
        '_token' => $session->token(),
        'action' => 'suspend',
    ];
    $res = callAdmin($app, 'POST', '/admin/manage-accounts/' . $pendingBuyer->id . '/action', $actionParams, $session, $admin);
    check('Users', 'POST /admin/manage-accounts/{id}/action (Suspend account button)', in_array($res->getStatusCode(), [200, 302]));

    $pendingBuyer->refresh();
    check('Users', 'Account successfully suspended', $pendingBuyer->status === 'suspended');

    // Re-activate for clean state
    callAdmin($app, 'POST', '/admin/manage-accounts/' . $pendingBuyer->id . '/action', ['_token' => $session->token(), 'action' => 'activate'], $session, $admin);

    // =============================================================
    // 3. SELLER ONBOARDING & AUDITING
    // =============================================================
    echo "\n--- Section 3: Seller Approvals & Audits ---\n";

    // GET /admin/sellers
    $res = callAdmin($app, 'GET', '/admin/sellers', [], $session, $admin);
    check('Sellers', 'GET /admin/sellers (Sellers directory loads)', $res->getStatusCode() === 200);

    // GET /admin/sellers/{seller}
    $res = callAdmin($app, 'GET', '/admin/sellers/' . $seller->id, [], $session, $admin);
    check('Sellers', 'GET /admin/sellers/{id} (Seller inspection detail view loads)', $res->getStatusCode() === 200);

    // POST /admin/sellers/{seller}/approve (Approve Seller Application button)
    $res = callAdmin($app, 'POST', '/admin/sellers/' . $seller->id . '/approve', ['_token' => $session->token()], $session, $admin);
    check('Sellers', 'POST /admin/sellers/{id}/approve (Approve seller button)', in_array($res->getStatusCode(), [200, 302]));

    $seller->refresh();
    check('Sellers', 'Seller approval_status updated to approved', $seller->approval_status === 'approved');

    // POST /admin/sellers/{seller}/suspend (Suspend Seller button)
    $res = callAdmin($app, 'POST', '/admin/sellers/' . $seller->id . '/suspend', ['_token' => $session->token()], $session, $admin);
    check('Sellers', 'POST /admin/sellers/{id}/suspend (Suspend seller button)', in_array($res->getStatusCode(), [200, 302]));

    // POST /admin/sellers/{seller}/reinstate (Reinstate Seller button)
    $res = callAdmin($app, 'POST', '/admin/sellers/' . $seller->id . '/reinstate', ['_token' => $session->token()], $session, $admin);
    check('Sellers', 'POST /admin/sellers/{id}/reinstate (Reinstate seller button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 4. RIDERS OVERVIEW
    // =============================================================
    echo "\n--- Section 4: Riders Directory ---\n";

    // GET /admin/riders
    $res = callAdmin($app, 'GET', '/admin/riders', [], $session, $admin);
    check('Riders', 'GET /admin/riders (Riders overview page loads)', $res->getStatusCode() === 200);

    // =============================================================
    // 5. PRODUCT & CATEGORY MANAGEMENT
    // =============================================================
    echo "\n--- Section 5: Products & Categories Management ---\n";

    // GET /admin/products
    $res = callAdmin($app, 'GET', '/admin/products', [], $session, $admin);
    check('Products', 'GET /admin/products (Marketplace products index loads)', $res->getStatusCode() === 200);

    // POST /admin/products/{id}/status (Toggle Product Status button)
    $res = callAdmin($app, 'POST', '/admin/products/' . $product->id . '/status', ['_token' => $session->token()], $session, $admin);
    check('Products', 'POST /admin/products/{id}/status (Toggle product status button)', in_array($res->getStatusCode(), [200, 302]));

    // GET /admin/categories
    $res = callAdmin($app, 'GET', '/admin/categories', [], $session, $admin);
    check('Categories', 'GET /admin/categories (Categories page loads)', $res->getStatusCode() === 200);

    // POST /admin/categories (Add Category button)
    $newCategoryParams = [
        '_token' => $session->token(),
        'name' => 'Sports & Fitness ' . Str::random(4),
        'icon' => 'activity',
    ];
    $res = callAdmin($app, 'POST', '/admin/categories', $newCategoryParams, $session, $admin);
    check('Categories', 'POST /admin/categories (Create category button)', in_array($res->getStatusCode(), [200, 302]));

    $createdCategory = Category::latest('id')->first();
    if ($createdCategory) {
        // PUT /admin/categories/{id} (Update Category button)
        $updateCategoryParams = [
            '_token' => $session->token(),
            'name' => $createdCategory->name . ' Updated',
        ];
        $res = callAdmin($app, 'PUT', '/admin/categories/' . $createdCategory->id, $updateCategoryParams, $session, $admin);
        check('Categories', 'PUT /admin/categories/{id} (Update category button)', in_array($res->getStatusCode(), [200, 302]));

        // POST /admin/categories/{id}/archive (Archive Category button)
        $res = callAdmin($app, 'POST', '/admin/categories/' . $createdCategory->id . '/archive', ['_token' => $session->token()], $session, $admin);
        check('Categories', 'POST /admin/categories/{id}/archive (Archive category button)', in_array($res->getStatusCode(), [200, 302]));

        // POST /admin/categories/{id}/restore (Restore Category button)
        $res = callAdmin($app, 'POST', '/admin/categories/' . $createdCategory->id . '/restore', ['_token' => $session->token()], $session, $admin);
        check('Categories', 'POST /admin/categories/{id}/restore (Restore category button)', in_array($res->getStatusCode(), [200, 302]));
    }

    // =============================================================
    // 6. ORDER OVERSIGHT
    // =============================================================
    echo "\n--- Section 6: Order Oversight ---\n";

    // GET /admin/orders
    $res = callAdmin($app, 'GET', '/admin/orders', [], $session, $admin);
    check('Orders', 'GET /admin/orders (Admin orders index loads)', $res->getStatusCode() === 200);

    // GET /admin/orders/{id}
    $res = callAdmin($app, 'GET', '/admin/orders/' . $order->id, [], $session, $admin);
    check('Orders', 'GET /admin/orders/{id} (Admin order detail view loads)', $res->getStatusCode() === 200);

    // POST /admin/orders/{id}/update (Update Order Status button)
    $orderUpdateParams = [
        '_token' => $session->token(),
        'status' => 'processing',
    ];
    $res = callAdmin($app, 'POST', '/admin/orders/' . $order->id . '/update', $orderUpdateParams, $session, $admin);
    check('Orders', 'POST /admin/orders/{id}/update (Update order status button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 7. PAYMENTS, ESCROW & WITHDRAWALS
    // =============================================================
    echo "\n--- Section 7: Payments, Escrow & Withdrawals ---\n";

    // GET /admin/payments
    $res = callAdmin($app, 'GET', '/admin/payments', [], $session, $admin);
    check('Payments', 'GET /admin/payments (Financial ledger loads)', $res->getStatusCode() === 200);

    // GET /admin/payments/gcash-escrow
    $res = callAdmin($app, 'GET', '/admin/payments/gcash-escrow', [], $session, $admin);
    check('Payments', 'GET /admin/payments/gcash-escrow (GCash escrow dashboard loads)', $res->getStatusCode() === 200);

    // POST /admin/payments/gcash-escrow/settings (Update GCash Escrow Settings button)
    $gcashSettingsParams = [
        '_token' => $session->token(),
        'account_name' => 'INVOIZ Official Escrow',
        'account_number' => '09170001111',
    ];
    $res = callAdmin($app, 'POST', '/admin/payments/gcash-escrow/settings', $gcashSettingsParams, $session, $admin);
    check('Payments', 'POST /admin/payments/gcash-escrow/settings (Update GCash settings button)', in_array($res->getStatusCode(), [200, 302]));

    // Test Withdrawal Approval / Rejection buttons
    $withdrawal = WithdrawalRequest::create([
        'seller_id' => $seller->id,
        'user_id' => $sellerUser->id,
        'amount' => 1500.00,
        'channel' => 'gcash',
        'account_name' => 'Candidate Store Owner',
        'account_number' => '09191234567',
        'status' => 'pending',
    ]);

    if ($withdrawal) {
        // POST /admin/payments/withdrawals/{id}/approve (Approve Withdrawal button)
        $res = callAdmin($app, 'POST', '/admin/payments/withdrawals/' . $withdrawal->id . '/approve', ['_token' => $session->token()], $session, $admin);
        check('Payments', 'POST /admin/payments/withdrawals/{id}/approve (Approve withdrawal button)', in_array($res->getStatusCode(), [200, 302]));
    }

    // =============================================================
    // 8. CUSTOMER COMPLAINTS & DISPUTES
    // =============================================================
    echo "\n--- Section 8: Complaints & Dispute Resolution ---\n";

    $complaint = Complaint::create([
        'buyer_id' => $pendingBuyer->id,
        'order_id' => $order->id,
        'seller_id' => $seller->id,
        'subject' => 'Delayed Delivery Issue',
        'description' => 'Delivery rider took longer than the estimated delivery window.',
        'status' => 'open',
        'type' => 'delivery',
    ]);

    // GET /admin/complaints
    $res = callAdmin($app, 'GET', '/admin/complaints', [], $session, $admin);
    check('Complaints', 'GET /admin/complaints (Complaints inbox loads)', $res->getStatusCode() === 200);

    // GET /admin/complaints/{id}
    $res = callAdmin($app, 'GET', '/admin/complaints/' . $complaint->id, [], $session, $admin);
    check('Complaints', 'GET /admin/complaints/{id} (Complaint detail loads)', $res->getStatusCode() === 200);

    // POST /admin/complaints/{id}/resolve (Resolve Complaint button)
    $resolveParams = [
        '_token' => $session->token(),
        'resolution' => 'Resolved with buyer and seller; parcel delivered.',
    ];
    $res = callAdmin($app, 'POST', '/admin/complaints/' . $complaint->id . '/resolve', $resolveParams, $session, $admin);
    check('Complaints', 'POST /admin/complaints/{id}/resolve (Resolve complaint button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 9. COMMISSION RATES
    // =============================================================
    echo "\n--- Section 9: Commission Rates Management ---\n";

    // GET /admin/commission
    $res = callAdmin($app, 'GET', '/admin/commission', [], $session, $admin);
    check('Commission', 'GET /admin/commission (Commission rates page loads)', $res->getStatusCode() === 200);

    // POST /admin/commission/rates/add (Add Rate button)
    $addRateParams = [
        '_token' => $session->token(),
        'category_id' => $category->id,
        'rate' => 3.5,
    ];
    $res = callAdmin($app, 'POST', '/admin/commission/rates/add', $addRateParams, $session, $admin);
    check('Commission', 'POST /admin/commission/rates/add (Add commission rate button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 10. REPORTS & EXPORTS
    // =============================================================
    echo "\n--- Section 10: Reports & Print Views ---\n";

    // GET /admin/reports
    $res = callAdmin($app, 'GET', '/admin/reports', [], $session, $admin);
    check('Reports', 'GET /admin/reports (Reports dashboard loads)', $res->getStatusCode() === 200);

    // GET /admin/reports/print
    $res = callAdmin($app, 'GET', '/admin/reports/print', [], $session, $admin);
    check('Reports', 'GET /admin/reports/print (Printable report layout loads)', $res->getStatusCode() === 200);

    // GET /admin/reports/commission
    $res = callAdmin($app, 'GET', '/admin/reports/commission', [], $session, $admin);
    check('Reports', 'GET /admin/reports/commission (Commission report redirects to commission settings)', in_array($res->getStatusCode(), [200, 302]), "HTTP " . $res->getStatusCode());

    // =============================================================
    // 11. PLATFORM SETTINGS & POLICIES
    // =============================================================
    echo "\n--- Section 11: Settings, Announcements & Policies ---\n";

    // GET /admin/settings
    $res = callAdmin($app, 'GET', '/admin/settings', [], $session, $admin);
    check('Settings', 'GET /admin/settings (Settings page loads)', $res->getStatusCode() === 200);

    // POST /admin/settings/update (Save Settings button)
    $settingsParams = [
        '_token' => $session->token(),
        'return_window_days' => 7,
        'platform_commission_percent' => 5,
    ];
    $res = callAdmin($app, 'POST', '/admin/settings/update', $settingsParams, $session, $admin);
    check('Settings', 'POST /admin/settings/update (Update platform settings button)', in_array($res->getStatusCode(), [200, 302]));

    // POST /admin/settings/announcement (Broadcast Announcement button)
    $announcementParams = [
        '_token' => $session->token(),
        'title' => 'Scheduled System Maintenance Notice',
        'message' => 'The platform will undergo routine maintenance at 2:00 AM.',
        'type' => 'info',
        'audience' => 'all',
    ];
    $res = callAdmin($app, 'POST', '/admin/settings/announcement', $announcementParams, $session, $admin);
    check('Settings', 'POST /admin/settings/announcement (Broadcast announcement button)', in_array($res->getStatusCode(), [200, 302]));

    // POST /admin/settings/policy (Create Policy button)
    $policyParams = [
        '_token' => $session->token(),
        'type' => 'terms',
        'title' => 'Terms of Marketplace Service',
        'content' => 'Standard terms and conditions governing buyer and seller transactions.',
    ];
    $res = callAdmin($app, 'POST', '/admin/settings/policy', $policyParams, $session, $admin);
    check('Settings', 'POST /admin/settings/policy (Create policy button)', in_array($res->getStatusCode(), [200, 302]));

    // =============================================================
    // 12. ADMIN SUPPORT CHAT
    // =============================================================
    echo "\n--- Section 12: Admin Support Chat ---\n";

    // GET /admin/chat
    $res = callAdmin($app, 'GET', '/admin/chat', [], $session, $admin);
    check('Chat', 'GET /admin/chat (Admin support desk loads)', $res->getStatusCode() === 200);

    // POST /admin/chat/send (Send Support Reply button)
    $chatParams = [
        '_token' => $session->token(),
        'recipient_id' => $pendingBuyer->id,
        'message' => 'Hello from INVOIZ Support. How can we assist you today?',
    ];
    $res = callAdmin($app, 'POST', '/admin/chat/send', $chatParams, $session, $admin);
    check('Chat', 'POST /admin/chat/send (Send support message button)', in_array($res->getStatusCode(), [200, 201, 302]));

} finally {
    // Clean rollback
    DB::rollBack();
    echo "\n[INFO] Database transaction rolled back cleanly; zero test data persisted.\n";
}

echo "\n====================================================================\n";
$total = count($testResults);
$passed = count(array_filter($testResults, fn($r) => $r['pass']));
$failed = $total - $passed;
echo "ADMIN FUNCTIONS AUDIT SUMMARY: Total {$total} checks: {$passed} Passed, {$failed} Failed.\n";
echo "====================================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);
