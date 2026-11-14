<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderVoucher;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

echo "====================================================================\n";
echo " INVOIZ: Verifying Voucher Functions on Website & Mobile App\n";
echo "====================================================================\n\n";

$passCount = 0;
$failCount = 0;

function report(string $channel, string $test, bool $ok, ?string $details = null) {
    global $passCount, $failCount;
    if ($ok) {
        $passCount++;
        echo " [PASS] [{$channel}] {$test}" . ($details ? " ({$details})" : "") . "\n";
    } else {
        $failCount++;
        echo " [FAIL] [{$channel}] {$test}" . ($details ? " - {$details}" : "") . "\n";
    }
}

DB::beginTransaction();

try {
    // 1. Setup a test buyer, seller, product, address
    $buyerUser = User::create([
        'name' => 'Voucher Test Buyer',
        'email' => 'voucher_buyer_' . uniqid() . '@invoiz.test',
        'phone' => '09171234567',
        'password' => Hash::make('password123'),
        'role' => 'buyer',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $sellerUser = User::create([
        'name' => 'Voucher Test Seller',
        'email' => 'voucher_seller_' . uniqid() . '@invoiz.test',
        'phone' => '09187654321',
        'password' => Hash::make('password123'),
        'role' => 'seller',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $seller = Seller::create([
        'user_id' => $sellerUser->id,
        'store_name' => 'Voucher Test Store ' . uniqid(),
        'business_name' => 'Voucher Test Business',
        'line_of_business' => 'Retail',
        'postal_code' => '1600',
        'status' => 'active',
        'approval_status' => 'approved',
        'address_line' => '123 Market St',
        'barangay' => 'San Antonio',
        'city' => 'Pasig',
        'province' => 'Metro Manila',
    ]);

    $product = Product::create([
        'seller_id' => $sellerUser->id,
        'category_id' => 1,
        'name' => 'Voucher Test Widget ' . uniqid(),
        'price' => 500.00,
        'stock' => 50,
        'status' => 'active',
    ]);

    $address = Address::create([
        'buyer_id' => $buyerUser->id,
        'user_id' => $buyerUser->id,
        'recipient_name' => 'Voucher Buyer Recipient',
        'phone' => '09171234567',
        'address_line' => '456 Buyer St',
        'barangay' => 'Ugong',
        'city' => 'Pasig',
        'province' => 'Metro Manila',
        'is_default' => true,
    ]);

    // Create 2 test vouchers: 1 Platform voucher (₱50 off) and 1 Store voucher (₱100 off, min spend ₱400)
    $platformVoucher = Voucher::create([
        'code' => 'PLAT' . strtoupper(substr(uniqid(), -6)),
        'name' => 'Platform ₱50 Off Voucher',
        'type' => 'fixed',
        'discount_type' => 'fixed',
        'value' => 50.00,
        'discount_value' => 50.00,
        'min_spend' => 0.00,
        'usage_limit' => 100,
        'used_count' => 0,
        'status' => 'active',
    ]);

    $storeVoucher = Voucher::create([
        'seller_id' => $seller->id,
        'code' => 'STR' . strtoupper(substr(uniqid(), -6)),
        'name' => 'Store ₱100 Off Voucher',
        'type' => 'fixed',
        'discount_type' => 'fixed',
        'value' => 100.00,
        'discount_value' => 100.00,
        'min_spend' => 400.00,
        'usage_limit' => 100,
        'used_count' => 0,
        'status' => 'active',
    ]);

    echo "--- Section 1: Mobile App Voucher APIs ---\n";

    $token = $buyerUser->createToken('mobile_test_token')->plainTextToken;
    $authHeaders = [
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        'HTTP_ACCEPT' => 'application/json',
    ];

    // 1. Mobile GET /api/vouchers
    $req = Request::create('/api/vouchers', 'GET', [], [], [], $authHeaders);
    $res = $app->handle($req);
    $data = json_decode($res->getContent(), true);
    $vouchersList = $data['vouchers'] ?? [];
    $foundPlatform = collect($vouchersList)->contains('id', $platformVoucher->id);
    $foundStore = collect($vouchersList)->contains('id', $storeVoucher->id);
    report('Mobile API', 'GET /api/vouchers lists active vouchers', $res->getStatusCode() === 200 && $foundPlatform && $foundStore, "HTTP {$res->getStatusCode()}, vouchers listed");

    // 2. Mobile POST /api/vouchers/{id}/claim
    $req = Request::create("/api/vouchers/{$storeVoucher->id}/claim", 'POST', [], [], [], $authHeaders);
    $res = $app->handle($req);
    $claimData = json_decode($res->getContent(), true);
    $mobileClaimId = $claimData['claim']['id'] ?? null;
    report('Mobile API', 'POST /api/vouchers/{id}/claim claims voucher to wallet', $res->getStatusCode() === 201 && $mobileClaimId !== null, "Claim ID: {$mobileClaimId}");

    // 3. Mobile GET /api/vouchers/claimed
    $req = Request::create('/api/vouchers/claimed', 'GET', [], [], [], $authHeaders);
    $res = $app->handle($req);
    $claimedData = json_decode($res->getContent(), true);
    $hasClaim = collect($claimedData['vouchers'] ?? [])->contains('id', $mobileClaimId);
    report('Mobile API', 'GET /api/vouchers/claimed lists claimed vouchers in wallet', $res->getStatusCode() === 200 && $hasClaim);

    // Add item to cart for mobile preview/checkout
    $cart = Cart::headerFor($buyerUser->id);
    $cartItem = CartItem::create([
        'cart_id' => $cart->id,
        'product_id' => $product->id,
        'quantity' => 1,
    ]);

    // 4. Mobile POST /api/vouchers/validate
    $req = Request::create('/api/vouchers/validate', 'POST', [
        'code' => $storeVoucher->code,
        'cart_item_ids' => [$cartItem->id],
    ], [], [], $authHeaders);
    $res = $app->handle($req);
    $valData = json_decode($res->getContent(), true);
    $validDiscount = (float) ($valData['discount'] ?? 0);
    report('Mobile API', 'POST /api/vouchers/validate validates code & computes discount', $res->getStatusCode() === 200 && $validDiscount == 100.0, "Discount: ₱{$validDiscount}");

    // 5. Mobile POST /api/orders/preview with voucher
    $req = Request::create('/api/orders/preview', 'POST', [
        'cart_item_ids' => [$cartItem->id],
        'voucher_claim_id' => $mobileClaimId,
    ], [], [], $authHeaders);
    $res = $app->handle($req);
    $prevData = json_decode($res->getContent(), true);
    $prevSavings = (float) ($prevData['quote']['discount'] ?? 0);
    report('Mobile API', 'POST /api/orders/preview applies claimed voucher to order quote', $res->getStatusCode() === 200 && $prevSavings == 100.0, "Savings: ₱{$prevSavings}");

    // 6. Mobile POST /api/orders/checkout with voucher
    $req = Request::create('/api/orders/checkout', 'POST', [
        'address_id' => $address->id,
        'payment_method' => 'cash_on_delivery',
        'cart_item_ids' => [$cartItem->id],
        'voucher_claim_id' => $mobileClaimId,
    ], [], [], $authHeaders);
    $res = $app->handle($req);
    $checkoutData = json_decode($res->getContent(), true);
    $orders = $checkoutData['orders'] ?? [];
    $firstOrderId = $orders[0]['id'] ?? null;
    $orderVoucher = $firstOrderId ? OrderVoucher::where('order_id', $firstOrderId)->first() : null;
    $claimUpdated = VoucherClaim::where('id', $mobileClaimId)->whereNotNull('used_at')->exists();
    report('Mobile API', 'POST /api/orders/checkout records voucher discount & consumes claim', 
        $res->getStatusCode() === 201 && $orderVoucher && $claimUpdated && (float)$orderVoucher->discount_amount == 100.0,
        "Order #{$firstOrderId}, Discount: ₱{$orderVoucher?->discount_amount}, Claim used: " . ($claimUpdated ? 'Yes' : 'No')
    );

    // 7. Verify mobile rejects reused voucher
    $req = Request::create("/api/vouchers/{$storeVoucher->id}/claim", 'POST', [], [], [], $authHeaders);
    $res = $app->handle($req);
    report('Mobile API', 'POST /api/vouchers/{id}/claim blocks reusing already used voucher', $res->getStatusCode() === 409, "HTTP {$res->getStatusCode()} Conflict/Blocked");

    echo "\n--- Section 2: Website (Web Browser) Voucher Functions ---\n";

    // Setup session driver for web requests
    $webSession = $app->make('session')->driver();
    $webSession->start();
    $webSession->put('buyer', [
        'id' => $buyerUser->id,
        'name' => $buyerUser->name,
        'first_name' => 'Voucher',
        'last_name' => 'Tester',
        'email' => $buyerUser->email,
    ]);

    function callWebRoute($app, $method, $uri, $params = [], $session = null, $user = null) {
        Auth::shouldUse('web');
        if ($user) {
            Auth::guard('web')->setUser($user);
            if ($session) {
                $session->put('buyer', [
                    'id' => $user->id,
                    'name' => $user->name,
                    'first_name' => 'Voucher',
                    'last_name' => 'Tester',
                    'email' => $user->email,
                ]);
                $session->put('login_web_' . sha1(User::class), $user->id);
            }
        }
        $req = Request::create($uri, $method, $params);
        if ($session) {
            $req->setLaravelSession($session);
        }
        return $app->handle($req);
    }

    // 8. Website GET /vouchers
    $res = callWebRoute($app, 'GET', '/vouchers', [], $webSession, $buyerUser);
    $html = $res->getContent();
    $seesPlatform = str_contains($html, $platformVoucher->name);
    report('Website', 'GET /vouchers displays available promotional vouchers', $res->getStatusCode() === 200 && $seesPlatform, "Page rendered with active vouchers");

    // 9. Website POST /vouchers/{voucher}/claim
    $res = callWebRoute($app, 'POST', "/vouchers/{$platformVoucher->id}/claim", ['_token' => $webSession->token()], $webSession, $buyerUser);
    $webClaim = VoucherClaim::where('buyer_id', $buyerUser->id)->where('voucher_id', $platformVoucher->id)->first();
    report('Website', 'POST /vouchers/{voucher}/claim claims voucher for buyer session', in_array($res->getStatusCode(), [200, 302]) && $webClaim !== null, "Claim ID: {$webClaim?->id}");

    // 10. Website Store Page vouchers tab GET /store/{id}?tab=vouchers
    $res = callWebRoute($app, 'GET', "/store/{$seller->id}?tab=vouchers", [], $webSession, $buyerUser);
    report('Website', 'GET /store/{id}?tab=vouchers displays store-specific voucher tab', $res->getStatusCode() === 200);

    // 11. Website Single-Product Checkout with Claimed Voucher
    $res = callWebRoute($app, 'POST', '/checkout', [
        '_token' => $webSession->token(),
        'checkout_mode' => 'single',
        'single' => $product->id,
        'qty' => 1,
        'address_id' => $address->id,
        'payment_method' => 'cod',
        'voucher_claim_ids' => [$webClaim?->id],
    ], $webSession, $buyerUser);

    if ($webSession->has('errors')) {
        echo "Web Checkout Errors: " . json_encode($webSession->get('errors')->all()) . "\n";
    }
    if ($webSession->has('error')) {
        echo "Web Checkout Error: " . $webSession->get('error') . "\n";
    }

    $webOrder = Order::where('buyer_id', $buyerUser->id)->latest('id')->first();
    $webOrderVoucher = $webOrder ? OrderVoucher::where('order_id', $webOrder->id)->where('voucher_id', $platformVoucher->id)->first() : null;
    $webClaimUsed = $webClaim ? VoucherClaim::where('id', $webClaim->id)->whereNotNull('used_at')->exists() : false;
    report('Website', 'POST /checkout applies claimed voucher, discounts order & marks claim used',
        in_array($res->getStatusCode(), [200, 302]) && $webOrderVoucher !== null && $webClaimUsed && (float)$webOrderVoucher->discount_amount == 50.0,
        "Order #{$webOrder?->id}, Discount: ₱{$webOrderVoucher?->discount_amount}, Claim used: " . ($webClaimUsed ? 'Yes' : 'No')
    );

    // 12. Verify website blocks reusing consumed voucher
    $res = callWebRoute($app, 'POST', "/vouchers/{$platformVoucher->id}/claim", ['_token' => $webSession->token()], $webSession, $buyerUser);
    $flashError = $webSession->get('error') ?: session('error');
    $claimStillSingle = VoucherClaim::where('buyer_id', $buyerUser->id)->where('voucher_id', $platformVoucher->id)->count() === 1;
    report('Website', 'POST /vouchers/{voucher}/claim rejects re-claiming already used voucher', 
        in_array($res->getStatusCode(), [200, 302]) && $claimStillSingle, 
        "Blocked duplicate claim, error: '{$flashError}'"
    );

} catch (\Throwable $e) {
    report('Exception', 'Unexpected error during verification', false, $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
} finally {
    DB::rollBack();
    echo "\n[INFO] Database transaction rolled back cleanly; zero test data persisted.\n\n";
}

echo "====================================================================\n";
echo "VOUCHER AUDIT SUMMARY: Total " . ($passCount + $failCount) . " checks: {$passCount} Passed, {$failCount} Failed.\n";
echo "====================================================================\n";
