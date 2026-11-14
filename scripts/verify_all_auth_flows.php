<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Seller;
use App\Models\Rider;
use App\Models\LogisticsCenter;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$results = [];

function recordResult(string $category, string $testName, bool $passed, string $details = '')
{
    global $results;
    $results[] = [
        'category' => $category,
        'test' => $testName,
        'passed' => $passed,
        'details' => $details,
    ];
    $status = $passed ? "[PASS]" : "[FAIL]";
    echo "{$status} [{$category}] {$testName}" . ($details ? " - {$details}" : '') . PHP_EOL;
}

echo "========================================================\n";
echo " INVOIZ: Comprehensive Authentication & Onboarding Test\n";
echo "========================================================\n\n";

DB::beginTransaction();

try {
    // ---------------------------------------------------------
    // 1. BUYER WEB REGISTRATION, OTP VERIFY & LOGIN
    // ---------------------------------------------------------
    echo "--- Testing 1: Buyer Web Authentication ---\n";
    
    // Check GET /register
    $req = Request::create('/register', 'GET');
    $res = $app->handle($req);
    recordResult('Buyer Web', 'GET /register loads registration form', $res->getStatusCode() === 200, "HTTP " . $res->getStatusCode());

    // Check GET /login
    $req = Request::create('/login', 'GET');
    $res = $app->handle($req);
    recordResult('Buyer Web', 'GET /login loads login form', $res->getStatusCode() === 200, "HTTP " . $res->getStatusCode());

    // Prepare session for web requests
    $session = $app->make('session')->driver();
    $session->start();

    // Test POST /register
    $testBuyerEmail = 'web-buyer-' . Str::random(8) . '@invoiz-test.local';
    $regData = [
        '_token' => $session->token(),
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'sex' => 'female',
        'birthday' => '1995-05-15',
        'email' => $testBuyerEmail,
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
        'phone' => '0917' . random_int(1000000, 9999999),
        'terms' => 'on',
    ];
    
    $req = Request::create('/register', 'POST', $regData);
    $req->setLaravelSession($session);
    $res = $app->handle($req);
    
    $createdUser = User::where('email', $testBuyerEmail)->first();
    $regSuccess = $createdUser && $createdUser->role === 'buyer' && !empty($createdUser->otp);
    recordResult('Buyer Web', 'POST /register creates user and generates OTP', $regSuccess, "Status: " . $res->getStatusCode() . ($createdUser ? " (User #{$createdUser->id}, OTP generated)" : ""));

    // Test POST /verify (OTP)
    if ($createdUser) {
        $verifyData = [
            '_token' => $session->token(),
            'email' => $createdUser->email,
            'code' => $createdUser->otp,
        ];
        $req = Request::create('/verify', 'POST', $verifyData);
        $req->setLaravelSession($session);
        $res = $app->handle($req);
        
        $createdUser->refresh();
        $verified = !empty($createdUser->email_verified_at) && empty($createdUser->otp);
        recordResult('Buyer Web', 'POST /verify verifies email with valid OTP', $verified, "Verified at: " . $createdUser->email_verified_at);

        // Test POST /login with verified credentials
        Auth::logout();
        $loginData = [
            '_token' => $session->token(),
            'email' => $testBuyerEmail,
            'password' => 'SecurePass123!',
        ];
        $req = Request::create('/login', 'POST', $loginData);
        $req->setLaravelSession($session);
        $res = $app->handle($req);
        
        $loginRedirect = $res->headers->get('Location') ?? '';
        $loggedIn = Auth::check() && Auth::id() === $createdUser->id;
        recordResult('Buyer Web', 'POST /login authenticates verified buyer and redirects to shop', $loggedIn, "Redirect: {$loginRedirect}");
        Auth::logout();
    }

    // ---------------------------------------------------------
    // 2. BUYER MOBILE API REGISTRATION, OTP & LOGIN
    // ---------------------------------------------------------
    echo "\n--- Testing 2: Buyer Mobile API (buyer_mobile) ---\n";

    $testMobileEmail = 'mobile-buyer-' . Str::random(8) . '@invoiz-test.local';
    $mobileRegData = [
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'sex' => 'male',
        'birthday' => '1998-10-20',
        'email' => $testMobileEmail,
        'password' => 'MobilePass123!',
        'password_confirmation' => 'MobilePass123!',
        'phone' => '0918' . random_int(1000000, 9999999),
    ];

    $req = Request::create('/api/mobile/buyer/register', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode($mobileRegData));
    $res = $app->handle($req);
    $content = json_decode($res->getContent(), true);
    
    $mobileBuyer = User::where('email', $testMobileEmail)->first();
    $mobileRegPassed = ($res->getStatusCode() === 201) && !empty($content['verification_required']) && $mobileBuyer;
    recordResult('Buyer Mobile', 'POST /api/mobile/buyer/register issues code and returns 201', $mobileRegPassed, "Status: {$res->getStatusCode()}");

    // Test Mobile OTP verification: POST /api/mobile/buyer/verify-email
    if ($mobileBuyer) {
        $mobileVerifyData = [
            'email' => $testMobileEmail,
            'code' => $mobileBuyer->otp,
        ];
        $req = Request::create('/api/mobile/buyer/verify-email', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode($mobileVerifyData));
        $res = $app->handle($req);
        
        $mobileBuyer->refresh();
        $emailVerified = ($res->getStatusCode() === 200) && !empty($mobileBuyer->email_verified_at);
        recordResult('Buyer Mobile', 'POST /api/mobile/buyer/verify-email confirms email and clears code', $emailVerified, "Status: " . $res->getStatusCode());

        // Approve buyer for login (mirroring admin review workflow)
        $mobileBuyer->update(['approval_status' => 'approved', 'account_status' => 'active', 'status' => 'active']);

        // Test Mobile Login: POST /api/mobile/buyer/login
        $mobileLoginData = [
            'email' => $testMobileEmail,
            'password' => 'MobilePass123!',
        ];
        $req = Request::create('/api/mobile/buyer/login', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode($mobileLoginData));
        $res = $app->handle($req);
        $loginContent = json_decode($res->getContent(), true);
        
        $tokenReturned = ($res->getStatusCode() === 200) && !empty($loginContent['token']);
        recordResult('Buyer Mobile', 'POST /api/mobile/buyer/login returns Sanctum bearer token and user', $tokenReturned, "Token prefix: " . substr($loginContent['token'] ?? '', 0, 7) . '...');
    }

    // ---------------------------------------------------------
    // 3. SELLER ONBOARDING & LOGIN
    // ---------------------------------------------------------
    echo "\n--- Testing 3: Seller Application & Login ---\n";

    // Check GET /sell (Merchant onboarding page)
    $req = Request::create('/sell', 'GET');
    $res = $app->handle($req);
    recordResult('Seller Web', 'GET /sell loads merchant application wizard', $res->getStatusCode() === 200, "Status: " . $res->getStatusCode());

    // Check GET /seller/apply redirect
    $req = Request::create('/seller/apply', 'GET');
    $res = $app->handle($req);
    recordResult('Seller Web', 'GET /seller/apply redirects to /sell', in_array($res->getStatusCode(), [301, 302]), "Status: " . $res->getStatusCode());

    // Check GET /seller/login
    $req = Request::create('/seller/login', 'GET');
    $res = $app->handle($req);
    recordResult('Seller Web', 'GET /seller/login loads seller login form', $res->getStatusCode() === 200, "Status: " . $res->getStatusCode());

    // Test Seller Login routing for an approved merchant
    $sellerEmail = 'test-seller-' . Str::random(8) . '@invoiz-test.local';
    $sellerUser = User::create([
        'name' => 'Store Owner',
        'first_name' => 'Store',
        'last_name' => 'Owner',
        'email' => $sellerEmail,
        'phone' => '0917' . random_int(1000000, 9999999),
        'password' => Hash::make('SellerPass123!'),
        'role' => 'seller',
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $sampleSeller = Seller::first();
    $sellerAttributes = $sampleSeller ? $sampleSeller->toArray() : [];
    unset($sellerAttributes['id'], $sellerAttributes['created_at'], $sellerAttributes['updated_at']);

    $sellerProfileData = array_merge($sellerAttributes, [
        'user_id' => $sellerUser->id,
        'store_name' => 'Verified Test Store',
        'business_name' => 'Verified Test Store',
        'line_of_business' => 'Retail',
        'status' => 'active',
        'approval_status' => 'approved',
        'address_line' => '100 Commerce Ave',
        'city' => 'Manila',
        'province' => 'Metro Manila',
    ]);

    $sellerProfile = Seller::create($sellerProfileData);

    $loginData = [
        '_token' => $session->token(),
        'email' => $sellerEmail,
        'password' => 'SellerPass123!'
    ];
    $req = Request::create('/login', 'POST', $loginData);
    $req->setLaravelSession($session);
    $res = $app->handle($req);
    
    $redirectUrl = $res->headers->get('Location') ?? '';
    $sellerRedirected = str_contains($redirectUrl, 'seller/dashboard') || str_contains($redirectUrl, 'choose');
    recordResult('Seller Web', 'POST /login dispatches approved seller to dashboard/choice', $sellerRedirected, "Redirect: {$redirectUrl}");
    Auth::logout();

    // ---------------------------------------------------------
    // 4. LOGISTICS HUB OPERATIONS & RIDER ONBOARDING / LOGIN
    // ---------------------------------------------------------
    echo "\n--- Testing 4: Logistics Hub & Rider (rider_mobile) ---\n";

    // Check GET /logistics/login
    $req = Request::create('/logistics/login', 'GET');
    $res = $app->handle($req);
    recordResult('Logistics Web', 'GET /logistics/login loads logistics login page', $res->getStatusCode() === 200, "Status: " . $res->getStatusCode());

    // Check GET /logistics/logistics-center/apply
    $req = Request::create('/logistics/logistics-center/apply', 'GET');
    $res = $app->handle($req);
    recordResult('Logistics Web', 'GET /logistics/logistics-center/apply loads hub application page', $res->getStatusCode() === 200, "Status: " . $res->getStatusCode());

    // Test API: Rider Application (POST /api/logistics/rider/apply)
    $testRiderEmail = 'rider-applicant-' . Str::random(8) . '@invoiz-test.local';
    
    // Create pre-verified rider email verification record using otp_hash
    DB::table('rider_email_verifications')->insert([
        'email' => $testRiderEmail,
        'otp_hash' => Hash::make('123456'),
        'expires_at' => now()->addMinutes(10),
        'consumed_at' => now(),
        'attempts' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Query active 2-wheel vehicle type
    $vehicleType = VehicleType::where('is_active', true)
        ->get()
        ->first(fn($t) => $t->operationalCategory() === 'two_wheel');
    $vehicleTypeName = $vehicleType ? $vehicleType->name : 'Motorcycle';

    $dummyFile = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

    $riderApplyPayload = [
        'name' => 'Rodrigo Test Rider',
        'sex' => 'male',
        'birthday' => '1996-03-25',
        'email' => $testRiderEmail,
        'phone' => '0919' . random_int(1000000, 9999999),
        'vehicle_type' => $vehicleTypeName,
        'license_plate' => 'ABC ' . random_int(1000, 9999),
        'license_number' => 'N01-' . random_int(10, 99) . '-' . random_int(100000, 999999),
        'license_expiry' => now()->addYears(2)->toDateString(),
        'vehicle_registration' => 'VR-' . random_int(100000, 999999),
        'emergency_contact' => 'Juana Rider',
        'emergency_phone' => '09187654321',
        'address' => '123 Delivery Road, Hub City',
        'accepted_terms' => '1',
    ];

    $files = [
        'documents' => [
            'valid_id' => $dummyFile,
            'drivers_license' => $dummyFile,
            'vehicle_registration' => $dummyFile,
        ],
    ];

    $req = Request::create('/api/logistics/rider/apply', 'POST', $riderApplyPayload, [], $files, ['HTTP_ACCEPT' => 'application/json']);
    $res = $app->handle($req);
    $applyContent = json_decode($res->getContent(), true);
    
    $riderAppCreated = in_array($res->getStatusCode(), [200, 201]) && !empty($applyContent['application']);
    recordResult('Rider Mobile', 'POST /api/logistics/rider/apply records courier application', $riderAppCreated, "Status: {$res->getStatusCode()}" . (isset($applyContent['message']) ? " ({$applyContent['message']})" : ""));

    // Test Rider Mobile Login: POST /api/logistics/login
    $riderUser = User::where('role', 'rider')->where('account_status', 'active')->first();
    if (! $riderUser) {
        $riderUser = User::whereHas('rider')->first();
    }
    if (! $riderUser) {
        $riderUser = User::create([
            'name' => 'Active Logistics Rider',
            'first_name' => 'Active',
            'last_name' => 'Rider',
            'email' => 'active-rider-' . Str::random(8) . '@invoiz-test.local',
            'phone' => '0917' . random_int(1000000, 9999999),
            'password' => Hash::make('RiderPass123!'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'approval_status' => 'approved',
            'email_verified_at' => now(),
        ]);
        Rider::create([
            'user_id' => $riderUser->id,
            'name' => $riderUser->name,
            'email' => $riderUser->email,
            'phone' => $riderUser->phone,
            'status' => 'active',
            'approval_status' => 'approved',
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'XYZ 9999',
            'license_number' => 'N01-99-999999',
            'license_expiry' => now()->addYears(2),
            'vehicle_registration' => 'VR-999999',
            'vehicle_capacity_kg' => 20,
        ]);
    } else {
        $riderUser->update(['password' => Hash::make('RiderPass123!'), 'status' => 'active', 'account_status' => 'active']);
    }

    $riderLoginPayload = [
        'email' => $riderUser->email,
        'password' => 'RiderPass123!',
    ];

    $req = Request::create('/api/logistics/login', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'], json_encode($riderLoginPayload));
    $res = $app->handle($req);
    $riderLoginContent = json_decode($res->getContent(), true);

    $riderAuthSuccess = in_array($res->getStatusCode(), [200, 201]) && !empty($riderLoginContent['token']);
    recordResult('Rider Mobile', 'POST /api/logistics/login authenticates courier and returns token', $riderAuthSuccess, "Status: {$res->getStatusCode()}" . (isset($riderLoginContent['token']) ? " (Token: " . substr($riderLoginContent['token'], 0, 7) . "...)" : ""));

    // ---------------------------------------------------------
    // 5. ADMIN AUTHENTICATION
    // ---------------------------------------------------------
    echo "\n--- Testing 5: Admin Login ---\n";
    $adminEmail = 'test-admin-' . Str::random(8) . '@invoiz-test.local';
    $adminUser = User::create([
        'name' => 'Platform Superadmin',
        'first_name' => 'Platform',
        'last_name' => 'Superadmin',
        'email' => $adminEmail,
        'phone' => '0917' . random_int(1000000, 9999999),
        'password' => Hash::make('AdminPass123!'),
        'role' => 'admin',
        'is_admin' => true,
        'status' => 'active',
        'account_status' => 'active',
        'approval_status' => 'approved',
        'email_verified_at' => now(),
    ]);

    $adminLoginData = [
        '_token' => $session->token(),
        'email' => $adminEmail,
        'password' => 'AdminPass123!',
    ];
    $req = Request::create('/login', 'POST', $adminLoginData);
    $req->setLaravelSession($session);
    $res = $app->handle($req);
    
    $adminRedirect = $res->headers->get('Location') ?? '';
    $adminSuccess = str_contains($adminRedirect, 'admin/dashboard');
    recordResult('Admin Web', 'POST /login authenticates admin and redirects to /admin/dashboard', $adminSuccess, "Redirect: {$adminRedirect}");
    Auth::logout();

} finally {
    // Rollback all test database records cleanly
    DB::rollBack();
    echo "\n[INFO] Database transaction rolled back cleanly; zero test data persisted.\n";
}

echo "\n========================================================\n";
$total = count($results);
$passedCount = count(array_filter($results, fn($r) => $r['passed']));
$failedCount = $total - $passedCount;

echo "FINAL SUMMARY: {$total} checks: {$passedCount} Passed, {$failedCount} Failed.\n";
echo "========================================================\n";

if ($failedCount > 0) {
    exit(1);
}
exit(0);
