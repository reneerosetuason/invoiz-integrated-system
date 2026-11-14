<?php

/**
 * Logistics Portal & Mobile App API Verification Suite
 */

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Address;
use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

echo "====================================================================\n";
echo " INVOIZ: Verifying ALL Logistics Pages, Buttons & Mobile Functions \n";
echo "====================================================================\n\n";

$passed = 0;
$failed = 0;

function check(bool $cond, string $msg, ?string $detail = null, ?\Symfony\Component\HttpFoundation\Response $res = null): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo " [PASS] $msg" . ($detail ? " ($detail)" : "") . "\n";
    } else {
        $failed++;
        $extra = "";
        if ($res) {
            $extra = " [HTTP " . $res->getStatusCode() . "]";
            $content = $res->getContent();
            if ($content && strlen($content) < 300) {
                $extra .= " - " . strip_tags($content);
            }
        }
        echo " [FAIL] $msg" . ($detail ? " ($detail)" : "") . "$extra\n";
    }
}

// Session instance for CSRF and web requests
$session = $app->make('session')->driver();
$session->start();

function handleReq(\Illuminate\Foundation\Application $app, Request $request, ?User $user = null, $session = null): \Symfony\Component\HttpFoundation\Response {
    Auth::forgetGuards();
    Auth::shouldUse('web');
    if ($user) {
        Auth::guard('web')->setUser($user);
        $request->setUserResolver(fn() => $user);
    }
    if ($session) {
        $request->setLaravelSession($session);
    }
    return $app->handle($request);
}

function handleApiReq(\Illuminate\Foundation\Application $app, Request $request, ?string $bearerToken = null): \Symfony\Component\HttpFoundation\Response {
    Auth::forgetGuards();
    Auth::shouldUse('sanctum');
    $request->headers->set('Accept', 'application/json');
    if ($bearerToken) {
        $request->headers->set('Authorization', 'Bearer ' . $bearerToken);
    }
    return $app->handle($request);
}

DB::beginTransaction();

try {
    // --- 0. Seed Test Fixtures ---
    $testCompany = LogisticsCompany::create([
        'name' => 'Verify Logistics Express',
        'status' => 'active',
    ]);

    $hubOrigin = LogisticsCenter::create([
        'name' => 'Metro Manila Central Hub',
        'address' => '123 Logistics Way',
        'city' => 'Manila',
        'province' => 'Metro Manila',
        'barangay' => 'Barangay 659',
        'logistics_company_id' => $testCompany->id,
        'is_active' => true,
    ]);

    $hubDestination = LogisticsCenter::create([
        'name' => 'Calamba Regional Sorting Hub',
        'address' => '456 Express Blvd',
        'city' => 'Calamba',
        'province' => 'Laguna',
        'barangay' => 'Real',
        'logistics_company_id' => $testCompany->id,
        'is_active' => true,
    ]);

    $serviceArea = ServiceArea::create([
        'logistics_center_id' => $hubOrigin->id,
        'name' => 'Manila North Zone',
        'city' => 'Manila',
        'province' => 'Metro Manila',
        'is_active' => true,
    ]);

    $staffUser = User::create([
        'name' => 'Logistics Origin Staff',
        'email' => 'staff_orig_' . Str::random(8) . '@invoiz.test',
        'password' => Hash::make('StaffSecret123!'),
        'phone' => '0917' . rand(1000000, 9999999),
        'role' => 'staff',
        'status' => 'active',
        'center_id' => $hubOrigin->id,
        'logistics_company_id' => $testCompany->id,
        'email_verified_at' => now(),
    ]);

    $destStaffUser = User::create([
        'name' => 'Logistics Dest Staff',
        'email' => 'staff_dest_' . Str::random(8) . '@invoiz.test',
        'password' => Hash::make('StaffSecret123!'),
        'phone' => '0917' . rand(1000000, 9999999),
        'role' => 'staff',
        'status' => 'active',
        'center_id' => $hubDestination->id,
        'logistics_company_id' => $testCompany->id,
        'email_verified_at' => now(),
    ]);

    // Canonical designated global logistics admin
    $adminUser = User::where('email', User::GLOBAL_LOGISTICS_ADMIN_EMAIL)->first();
    if (!$adminUser) {
        $adminUser = User::create([
            'id' => User::GLOBAL_LOGISTICS_ADMIN_ID,
            'name' => 'Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'password' => Hash::make('LogAdmin123!'),
            'role' => 'logistics_admin',
            'status' => 'active',
            'is_admin' => 0,
            'email_verified_at' => now(),
        ]);
    }

    $riderUser = User::create([
        'name' => 'Verified Courier Rider',
        'email' => 'rider_' . Str::random(8) . '@invoiz.test',
        'password' => Hash::make('RiderSecret123!'),
        'phone' => '0919' . rand(1000000, 9999999),
        'role' => 'rider',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $rider = Rider::create([
        'user_id' => $riderUser->id,
        'center_id' => $hubOrigin->id,
        'service_area_id' => $serviceArea->id,
        'name' => $riderUser->name,
        'email' => $riderUser->email,
        'phone' => $riderUser->phone,
        'vehicle_type' => 'motorcycle',
        'vehicle_capacity_kg' => 25.0,
        'license_plate' => 'VER-1234',
        'license_number' => 'N01-12-123456',
        'status' => 'available',
        'vehicle_verification' => 'verified',
        'is_verified' => true,
        'approved_at' => now(),
    ]);

    $sellerUser = User::create([
        'name' => 'Merchant Sender',
        'email' => 'merchant_' . Str::random(8) . '@invoiz.test',
        'password' => Hash::make('MerchantSecret123!'),
        'phone' => '0921' . rand(1000000, 9999999),
        'role' => 'seller',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $testTracking = 'TRK-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4));
    $testDelivery = Delivery::create([
        'tracking_number' => $testTracking,
        'seller_id' => $sellerUser->id,
        'center_id' => $hubOrigin->id,
        'destination_center_id' => $hubDestination->id,
        'service_area_id' => $serviceArea->id,
        'sender_name' => 'Merchant Sender',
        'sender_phone' => '09211234567',
        'sender_address' => 'Merchant Warehouse, Manila',
        'recipient_name' => 'Customer Recipient',
        'recipient_phone' => '09201234567',
        'recipient_address' => 'Customer Home, Laguna',
        'status' => 'waiting_for_rider',
        'parcel_status' => 'pending_arrival',
        'weight' => 1.5,
        'length_cm' => 20,
        'width_cm' => 15,
        'height_cm' => 10,
        'payment_method' => 'cod',
    ]);

    DeliveryItem::create([
        'delivery_id' => $testDelivery->id,
        'name' => 'Verified Test Item',
        'quantity' => 1,
        'price' => 250.00,
    ]);

    $token = $session->token();

    echo "\n--- Section 1: Public Entry & Center Application Wizard ---\n";
    $res = handleReq($app, Request::create('/logistics', 'GET'), null, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Public] GET /logistics', 'Landing/redirect HTTP ' . $res->getStatusCode(), $res);

    $res = handleReq($app, Request::create('/logistics/logistics-center/apply', 'GET'), null, $session);
    check($res->getStatusCode() === 200, '[Public] GET /logistics/logistics-center/apply', 'Center application wizard loads', $res);

    $res = handleReq($app, Request::create('/logistics/logistics-center/application-status', 'GET'), null, $session);
    check($res->getStatusCode() === 200, '[Public] GET /logistics/logistics-center/application-status', 'Center status check page loads', $res);

    $res = handleReq($app, Request::create('/logistics/logistics-center/verification/send', 'POST', [
        '_token' => $token,
        'email' => 'new-franchise-' . Str::random(6) . '@test.local',
    ]), null, $session);
    check(in_array($res->getStatusCode(), [200, 302, 422]), '[Public] POST /logistics/logistics-center/verification/send', 'Email verification OTP trigger', $res);

    echo "\n--- Section 2: Staff & Admin Dashboard & Metrics ---\n";
    $res = handleReq($app, Request::create('/logistics/dashboard', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Dashboard] GET /logistics/dashboard', 'Staff dashboard & metrics load', $res);

    $res = handleReq($app, Request::create('/logistics/notifications', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Notifications] GET /logistics/notifications', 'Hub notifications page loads', $res);

    $res = handleReq($app, Request::create('/logistics/tracking', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Tracking] GET /logistics/tracking', 'Shipment tracking page loads', $res);

    $res = handleReq($app, Request::create('/logistics/settings', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Settings] GET /logistics/settings', 'Logistics hub settings page loads', $res);

    echo "\n--- Section 3: Operations & Sorting Center Workspaces ---\n";
    $res = handleReq($app, Request::create('/logistics/operations', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Operations] GET /logistics/operations', 'Unified operations queue loads', $res);

    $res = handleReq($app, Request::create('/logistics/sorting-center', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Sorting] GET /logistics/sorting-center', 'Sorting center overview loads', $res);

    echo "\n--- Section 4: Deliveries Lifecycle & QR Scanner Operations ---\n";
    $res = handleReq($app, Request::create('/logistics/deliveries', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Deliveries] GET /logistics/deliveries', 'Deliveries directory loads', $res);

    $res = handleReq($app, Request::create('/logistics/deliveries/scan', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Scanner] GET /logistics/deliveries/scan', 'Barcode/QR scanner interface loads', $res);

    $res = handleReq($app, Request::create('/logistics/deliveries/' . $testDelivery->id, 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Deliveries] GET /logistics/deliveries/{id}', 'Parcel inspection view loads', $res);

    // Receive parcel at hub
    $res = handleReq($app, Request::create('/logistics/deliveries/' . $testDelivery->id . '/receive', 'POST', [
        '_token' => $token,
        'center_id' => $hubOrigin->id,
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Deliveries] POST /logistics/deliveries/{id}/receive', 'Receive parcel button works', $res);

    $testDelivery->refresh();
    check($testDelivery->received_at !== null || $testDelivery->parcel_status === 'received', '[Deliveries] Parcel marked received at hub');

    // QR Lookup after receipt
    $scanLookupReq = Request::create('/logistics/deliveries/scan-lookup', 'POST', [
        '_token' => $token,
        'tracking_number' => $testDelivery->tracking_number,
    ]);
    $scanLookupReq->headers->set('Accept', 'application/json');
    $scanLookupReq->headers->set('X-Requested-With', 'XMLHttpRequest');
    $res = handleReq($app, $scanLookupReq, $staffUser, $session);
    check($res->getStatusCode() === 200, '[Scanner] POST /logistics/deliveries/scan-lookup', 'Parcel QR lookup works', $res);

    // QR Verify scan into hub custody
    $scanVerifyReq = Request::create('/logistics/deliveries/scan-verify', 'POST', [
        '_token' => $token,
        'tracking_number' => $testDelivery->tracking_number,
    ]);
    $scanVerifyReq->headers->set('Accept', 'application/json');
    $scanVerifyReq->headers->set('X-Requested-With', 'XMLHttpRequest');
    $res = handleReq($app, $scanVerifyReq, $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Scanner] POST /logistics/deliveries/scan-verify', 'Scan & verify barcode into hub custody', $res);

    // Sort parcel
    $res = handleReq($app, Request::create('/logistics/deliveries/' . $testDelivery->id . '/sort', 'POST', [
        '_token' => $token,
        'destination_center_id' => $hubDestination->id,
        'service_area_id' => $serviceArea->id,
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Deliveries] POST /logistics/deliveries/{id}/sort', 'Sort parcel button works', $res);

    // Assign courier rider
    $res = handleReq($app, Request::create('/logistics/deliveries/' . $testDelivery->id . '/assign-rider', 'POST', [
        '_token' => $token,
        'rider_id' => $rider->id,
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Deliveries] POST /logistics/deliveries/{id}/assign-rider', 'Assign courier rider button works', $res);

    // Report exception
    $res = handleReq($app, Request::create('/logistics/deliveries/' . $testDelivery->id . '/exception', 'POST', [
        '_token' => $token,
        'exception_type' => 'customer_unreachable',
        'severity' => 'low',
        'notes' => 'Customer phone temporarily out of service.',
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Deliveries] POST /logistics/deliveries/{id}/exception', 'Log exception button works', $res);

    echo "\n--- Section 5: Bulk Operations Execution ---\n";
    $bulkDelivery = Delivery::create([
        'tracking_number' => 'TRK-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
        'seller_id' => $sellerUser->id,
        'center_id' => $hubOrigin->id,
        'destination_center_id' => $hubDestination->id,
        'service_area_id' => $serviceArea->id,
        'sender_name' => 'Merchant Sender',
        'recipient_name' => 'Bulk Recipient',
        'status' => 'waiting_for_rider',
        'parcel_status' => 'pending_arrival',
        'payment_method' => 'cod',
    ]);

    $res = handleReq($app, Request::create('/logistics/operations/bulk-receive', 'POST', [
        '_token' => $token,
        'confirmed' => '1',
        'delivery_ids' => [$bulkDelivery->id],
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Bulk] POST /logistics/operations/bulk-receive', 'Bulk receive parcels button works', $res);

    $bulkDelivery->update(['parcel_status' => 'received', 'received_at' => now()]);

    $res = handleReq($app, Request::create('/logistics/operations/bulk-sort', 'POST', [
        '_token' => $token,
        'confirmed' => '1',
        'delivery_ids' => [$bulkDelivery->id],
        'destination_center_id' => $hubDestination->id,
        'service_area_id' => $serviceArea->id,
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Bulk] POST /logistics/operations/bulk-sort', 'Bulk sort parcels button works', $res);

    $bulkDelivery->update(['parcel_status' => 'sorted', 'sorted_at' => now()]);

    $res = handleReq($app, Request::create('/logistics/operations/bulk-assign-rider', 'POST', [
        '_token' => $token,
        'confirmed' => '1',
        'rider_id' => $rider->id,
        'delivery_ids' => [$bulkDelivery->id],
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Bulk] POST /logistics/operations/bulk-assign-rider', 'Bulk assign rider button works', $res);

    echo "\n--- Section 6: Inter-Hub Transfers ---\n";
    $res = handleReq($app, Request::create('/logistics/hub-transfers', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Hub Transfer] GET /logistics/hub-transfers', 'Hub transfers overview loads', $res);

    $res = handleReq($app, Request::create('/logistics/hub-transfers/create', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Hub Transfer] GET /logistics/hub-transfers/create', 'Create transfer manifest form loads', $res);

    $truck = LogisticsTruck::create([
        'logistics_company_id' => $testCompany->id,
        'plate_number' => 'TRK-' . Str::upper(Str::random(6)),
        'name' => 'Inter-Hub Truck #1',
        'vehicle_type' => 'truck',
        'parcel_capacity' => 500,
        'capacity_kg' => 3500.0,
        'status' => 'assigned',
    ]);

    $transportDriver = HubTransportDriver::create([
        'name' => 'Transport Driver Juan',
        'email' => 'driver_' . Str::random(6) . '@invoiz.test',
        'phone' => '0917' . rand(1000000, 9999999),
        'logistics_company_id' => $testCompany->id,
        'logistics_truck_id' => $truck->id,
        'status' => 'assigned',
        'is_online' => true,
    ]);

    $transferDelivery = Delivery::create([
        'tracking_number' => 'TRK-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
        'seller_id' => $sellerUser->id,
        'center_id' => $hubOrigin->id,
        'destination_center_id' => $hubDestination->id,
        'service_area_id' => $serviceArea->id,
        'sender_name' => 'Merchant Sender',
        'recipient_name' => 'Transfer Recipient',
        'status' => 'waiting_for_rider',
        'parcel_status' => 'sorted',
        'weight' => 2.5,
        'payment_method' => 'cod',
    ]);

    $hubTransfer = HubTransfer::create([
        'reference' => 'HTF-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
        'logistics_company_id' => $testCompany->id,
        'origin_center_id' => $hubOrigin->id,
        'destination_center_id' => $hubDestination->id,
        'truck_id' => $truck->id,
        'driver_id' => $transportDriver->id,
        'status' => 'loading',
        'created_by' => $staffUser->id,
    ]);

    HubTransferParcel::create([
        'hub_transfer_id' => $hubTransfer->id,
        'delivery_id' => $transferDelivery->id,
        'loading_status' => 'pending',
    ]);

    $res = handleReq($app, Request::create('/logistics/hub-transfers/' . $hubTransfer->id, 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Hub Transfer] GET /logistics/hub-transfers/{id}', 'Hub transfer manifest details load', $res);

    $res = handleReq($app, Request::create('/logistics/hub-transfers/' . $hubTransfer->id . '/load', 'POST', [
        '_token' => $token,
        'tracking_number' => $transferDelivery->tracking_number,
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Hub Transfer] POST /logistics/hub-transfers/{id}/load', 'Load parcel onto truck manifest', $res);

    $hubTransfer->refresh();

    $res = handleReq($app, Request::create('/logistics/hub-transfers/' . $hubTransfer->id . '/dispatch', 'POST', [
        '_token' => $token,
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Hub Transfer] POST /logistics/hub-transfers/{id}/dispatch', 'Dispatch truck to destination hub', $res);

    $hubTransfer->refresh();

    $res = handleReq($app, Request::create('/logistics/hub-transfers/' . $hubTransfer->id . '/arrive', 'POST', [
        '_token' => $token,
    ]), $destStaffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Hub Transfer] POST /logistics/hub-transfers/{id}/arrive', 'Arrive truck at destination hub', $res);

    $hubTransfer->refresh();

    $res = handleReq($app, Request::create('/logistics/hub-transfers/' . $hubTransfer->id . '/receive', 'POST', [
        '_token' => $token,
        'tracking_number' => $transferDelivery->tracking_number,
        'condition' => 'good',
    ]), $destStaffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Hub Transfer] POST /logistics/hub-transfers/{id}/receive', 'Receive transferred parcel at destination hub', $res);

    echo "\n--- Section 7: Merchant Pickup Requests ---\n";
    $res = handleReq($app, Request::create('/logistics/pickup-requests', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Pickups] GET /logistics/pickup-requests', 'Pickup requests queue loads', $res);

    $pickupDelivery = Delivery::create([
        'tracking_number' => 'TRK-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
        'seller_id' => $sellerUser->id,
        'center_id' => $hubOrigin->id,
        'status' => 'waiting_for_rider',
        'payment_method' => 'cod',
    ]);

    $pickupReq = PickupRequest::create([
        'delivery_id' => $pickupDelivery->id,
        'center_id' => $hubOrigin->id,
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $res = handleReq($app, Request::create('/logistics/pickup-requests/' . $pickupReq->id, 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Pickups] GET /logistics/pickup-requests/{id}', 'Pickup request detail view loads', $res);

    $res = handleReq($app, Request::create('/logistics/pickup-requests/' . $pickupReq->id . '/approve', 'POST', [
        '_token' => $token,
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Pickups] POST /logistics/pickup-requests/{id}/approve', 'Approve pickup request button works', $res);

    echo "\n--- Section 8: Rider Directory & Applications Management ---\n";
    $res = handleReq($app, Request::create('/logistics/riders', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Riders] GET /logistics/riders', 'Riders roster loads', $res);

    $res = handleReq($app, Request::create('/logistics/riders/' . $rider->id, 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Riders] GET /logistics/riders/{id}', 'Rider profile inspection loads', $res);

    $res = handleReq($app, Request::create('/logistics/riders/' . $rider->id . '/deactivate', 'POST', [
        '_token' => $token,
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Riders] POST /logistics/riders/{id}/deactivate', 'Deactivate rider button works', $res);

    $res = handleReq($app, Request::create('/logistics/riders/' . $rider->id . '/activate', 'POST', [
        '_token' => $token,
    ]), $staffUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Riders] POST /logistics/riders/{id}/activate', 'Activate rider button works', $res);

    $res = handleReq($app, Request::create('/logistics/rider-applications', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Rider Apps] GET /logistics/rider-applications', 'Rider applications list loads', $res);

    $riderApp = RiderApplication::create([
        'name' => 'Applicant Rider Test',
        'email' => 'app_rider_' . Str::random(6) . '@invoiz.test',
        'phone' => '0917' . rand(1000000, 9999999),
        'vehicle_type' => 'motorcycle',
        'status' => 'pending',
        'application_type' => 'delivery_rider',
    ]);

    $res = handleReq($app, Request::create('/logistics/rider-applications/' . $riderApp->id, 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Rider Apps] GET /logistics/rider-applications/{id}', 'Rider application detail loads', $res);

    $res = handleReq($app, Request::create('/logistics/document-change-requests', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Rider Docs] GET /logistics/document-change-requests', 'Rider document change requests load', $res);

    $res = handleReq($app, Request::create('/logistics/hub-transport-drivers', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Hub Drivers] GET /logistics/hub-transport-drivers', 'Hub transport drivers directory loads', $res);

    echo "\n--- Section 9: Fleet & Trucks Management (Admin) ---\n";
    $res = handleReq($app, Request::create('/logistics/fleet', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Fleet] GET /logistics/fleet', 'Fleet & vehicles dashboard loads', $res);

    $res = handleReq($app, Request::create('/logistics/fleet/trucks', 'POST', [
        '_token' => $token,
        'plate_number' => 'NWT-' . Str::upper(Str::random(5)),
        'name' => 'L300 Cargo Van #5',
        'vehicle_type' => 'van',
        'parcel_capacity' => 150,
        'capacity_kg' => 1200.0,
        'status' => 'available',
    ]), $adminUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Fleet] POST /logistics/fleet/trucks', 'Add truck to fleet button works', $res);

    $res = handleReq($app, Request::create('/logistics/fleet/trucks/' . $truck->id, 'PUT', [
        '_token' => $token,
        'plate_number' => $truck->plate_number,
        'name' => 'Updated Truck #1',
        'vehicle_type' => 'truck',
        'parcel_capacity' => 550,
        'capacity_kg' => 3800.0,
        'status' => 'available',
    ]), $adminUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Fleet] PUT /logistics/fleet/trucks/{id}', 'Update truck specifications button works', $res);

    echo "\n--- Section 10: Logistics Companies, Centers & Service Areas ---\n";
    $res = handleReq($app, Request::create('/logistics/companies', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Companies] GET /logistics/companies', 'Logistics companies tree loads', $res);

    $res = handleReq($app, Request::create('/logistics/centers', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Centers] GET /logistics/centers', 'Logistics centers directory loads', $res);

    $extraCenter = LogisticsCenter::create([
        'name' => 'Temporary Toggle Hub',
        'code' => 'TOGGLE-' . Str::upper(Str::random(4)),
        'address' => 'Temporary Address',
        'city' => 'Manila',
        'phone' => '09171112222',
        'is_active' => true,
        'logistics_company_id' => $testCompany->id,
    ]);

    $res = handleReq($app, Request::create('/logistics/centers/' . $extraCenter->id . '/toggle', 'POST', [
        '_token' => $token,
    ]), $adminUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Centers] POST /logistics/centers/{id}/toggle', 'Toggle center operational status button', $res);

    $res = handleReq($app, Request::create('/logistics/service-areas', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Service Areas] GET /logistics/service-areas', 'Service coverage areas load', $res);

    $res = handleReq($app, Request::create('/logistics/service-areas/' . $serviceArea->id . '/toggle', 'POST', [
        '_token' => $token,
    ]), $adminUser, $session);
    check(in_array($res->getStatusCode(), [200, 302]), '[Service Areas] POST /logistics/service-areas/{id}/toggle', 'Toggle service area active status button', $res);

    $res = handleReq($app, Request::create('/logistics/staff', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Staff] GET /logistics/staff', 'Staff members directory loads', $res);

    echo "\n--- Section 11: Finance, Remittances & Reports ---\n";
    $res = handleReq($app, Request::create('/logistics/transactions', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Finance] GET /logistics/transactions', 'Financial transaction ledger loads', $res);

    $res = handleReq($app, Request::create('/logistics/remittances', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Finance] GET /logistics/remittances', 'Remittances dashboard loads', $res);

    $res = handleReq($app, Request::create('/logistics/hub-payouts', 'GET'), $staffUser, $session);
    check($res->getStatusCode() === 200, '[Finance] GET /logistics/hub-payouts', 'Hub payouts overview loads', $res);

    $res = handleReq($app, Request::create('/logistics/reports', 'GET'), $adminUser, $session);
    check($res->getStatusCode() === 200, '[Reports] GET /logistics/reports', 'Logistics performance reports load', $res);

    echo "\n--- Section 12: Rider Mobile App Authentication & Profile APIs ---\n";
    $res = handleApiReq($app, Request::create('/api/logistics/login', 'POST', [
        'email' => $riderUser->email,
        'password' => 'RiderSecret123!',
    ]));
    check($res->getStatusCode() === 200, '[Mobile Auth] POST /api/logistics/login', 'Rider mobile login generates Sanctum token', $res);

    $loginData = json_decode($res->getContent(), true);
    $bearerToken = $loginData['token'] ?? null;
    check(!empty($bearerToken), '[Mobile Auth] Sanctum bearer token issued');

    $res = handleApiReq($app, Request::create('/api/logistics/user', 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile User] GET /api/logistics/user', 'Fetch authenticated rider identity', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/profile', 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Profile] GET /api/logistics/rider/profile', 'Rider mobile profile details load', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/profile', 'PATCH', [
        'phone' => '09199998888',
    ]), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Profile] PATCH /api/logistics/rider/profile', 'Update rider phone via mobile API', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/status', 'PATCH', [
        'status' => 'online',
    ]), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Status] PATCH /api/logistics/rider/status', 'Toggle rider duty status (online/offline)', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/location', 'POST', [
        'latitude' => 14.5995,
        'longitude' => 120.9842,
    ]), $bearerToken);
    check(in_array($res->getStatusCode(), [200, 201]), '[Mobile Location] POST /api/logistics/rider/location', 'Record rider live GPS coordinates', $res);

    echo "\n--- Section 13: Rider Mobile Deliveries Lifecycle ---\n";
    $deliveryAddress = Address::create([
        'buyer_id' => $sellerUser->id,
        'recipient_name' => 'Metro Buyer',
        'phone' => '09171234567',
        'address_line' => '123 Test St',
        'barangay' => 'Barangay 1',
        'city' => 'Manila',
        'province' => 'Metro Manila',
        'is_default' => true,
    ]);

    $deliveryOrder = Order::create([
        'order_number' => 'ORD-' . strtoupper(Str::random(8)),
        'address_id' => $deliveryAddress->id,
        'seller_id' => $sellerUser->id,
        'buyer_id' => $sellerUser->id,
        'status' => 'ready_for_delivery',
        'payment_status' => 'pending',
        'total' => 300.00,
        'total_amount' => 300.00,
        'sub_total' => 300.00,
        'shipping_fee' => 0.00,
    ]);

    $product = \App\Models\Product::first();
    $productId = $product ? $product->id : \App\Models\Product::create([
        'seller_id' => $sellerUser->id,
        'name' => 'Courier Express Item',
        'price' => 300.00,
        'stock' => 10,
    ])->id;

    OrderItem::create([
        'order_id' => $deliveryOrder->id,
        'seller_id' => $sellerUser->id,
        'product_id' => $productId,
        'quantity' => 1,
        'price' => 300.00,
        'product_name' => 'Express Courier Item',
    ]);

    $assignedDelivery = Delivery::create([
        'order_id' => $deliveryOrder->id,
        'tracking_number' => 'TRK-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
        'seller_id' => $sellerUser->id,
        'rider_id' => $rider->id,
        'center_id' => $hubOrigin->id,
        'destination_center_id' => $hubOrigin->id,
        'service_area_id' => $serviceArea->id,
        'sender_name' => 'Metro Merchant',
        'sender_address' => 'Binondo, Manila',
        'recipient_name' => 'Metro Buyer',
        'recipient_address' => 'Ermita, Manila',
        'status' => 'assigned',
        'payment_method' => 'cod',
        'amount_to_collect' => 300.00,
    ]);

    PickupRequest::create([
        'delivery_id' => $assignedDelivery->id,
        'center_id' => $hubOrigin->id,
        'status' => 'approved',
        'requested_at' => now(),
    ]);

    DeliveryItem::create([
        'delivery_id' => $assignedDelivery->id,
        'name' => 'Express Courier Item',
        'quantity' => 1,
        'price' => 300.00,
    ]);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/deliveries', 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Deliveries] GET /api/logistics/rider/deliveries', 'Rider active task list loads', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/deliveries/lookup', 'GET', [
        'tracking_number' => $assignedDelivery->tracking_number,
    ]), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Deliveries] GET /api/logistics/rider/deliveries/lookup', 'Rider delivery lookup by tracking', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/deliveries/' . $assignedDelivery->id, 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Deliveries] GET /api/logistics/rider/deliveries/{id}', 'Rider delivery detail loads', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/deliveries/' . $assignedDelivery->id . '/accept', 'POST'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Deliveries] POST /api/logistics/rider/deliveries/{id}/accept', 'Rider accept delivery button works', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/deliveries/' . $assignedDelivery->id . '/status', 'PATCH', [
        'status' => 'going_to_pickup',
    ]), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Deliveries] PATCH /api/logistics/rider/deliveries/{id}/status (going_to_pickup)', 'Transition to going_to_pickup', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/deliveries/' . $assignedDelivery->id . '/pickup', 'POST'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Deliveries] POST /api/logistics/rider/deliveries/{id}/pickup', 'Rider confirm parcel pickup button works', $res);

    $assignedDelivery->update([
        'sorting_center_pickup_at' => now(),
        'sorting_center_pickup_rider_id' => $rider->id,
    ]);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/deliveries/' . $assignedDelivery->id . '/status', 'PATCH', [
        'status' => 'out_for_delivery',
    ]), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Deliveries] PATCH /api/logistics/rider/deliveries/{id}/status (out_for_delivery)', 'Transition to out_for_delivery', $res);

    $fakeProofPhoto = UploadedFile::fake()->create('proof_delivery.jpg', 150, 'image/jpeg');
    $res = handleApiReq($app, Request::create(
        '/api/logistics/rider/deliveries/' . $assignedDelivery->id . '/complete',
        'POST',
        [
            'amount_received' => 300.00,
            'signature_name' => 'Metro Buyer Signature',
        ],
        [],
        ['photo' => $fakeProofPhoto]
    ), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Deliveries] POST /api/logistics/rider/deliveries/{id}/complete', 'Complete delivery with photo proof & COD button', $res);

    $failDelivery = Delivery::create([
        'tracking_number' => 'TRK-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
        'seller_id' => $sellerUser->id,
        'rider_id' => $rider->id,
        'center_id' => $hubOrigin->id,
        'destination_center_id' => $hubOrigin->id,
        'service_area_id' => $serviceArea->id,
        'sender_name' => 'Metro Merchant',
        'recipient_name' => 'Metro Buyer',
        'status' => 'out_for_delivery',
        'payment_method' => 'cod',
    ]);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/deliveries/' . $failDelivery->id . '/failed', 'POST', [
        'reason' => 'Customer unreachable after multiple calls',
        'notes' => 'Customer address inaccessible due to road work.',
    ]), $bearerToken);
    check(in_array($res->getStatusCode(), [200, 201]), '[Mobile Deliveries] POST /api/logistics/rider/deliveries/{id}/failed', 'Report failed delivery attempt button', $res);

    echo "\n--- Section 14: Mobile Scanner, Financials, Messages & Notifications ---\n";
    $res = handleApiReq($app, Request::create('/api/logistics/rider/earnings', 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Finance] GET /api/logistics/rider/earnings', 'Rider earnings dashboard loads', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/history', 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Finance] GET /api/logistics/rider/history', 'Rider delivery history loads', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/remittances', 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Finance] GET /api/logistics/rider/remittances', 'Rider COD remittances overview loads', $res);

    // Mobile scan context for staff (staff mobile login)
    $resStaffLogin = handleApiReq($app, Request::create('/api/logistics/login', 'POST', [
        'email' => $staffUser->email,
        'password' => 'StaffSecret123!',
    ]));
    $staffToken = json_decode($resStaffLogin->getContent(), true)['token'] ?? null;
    $res = handleApiReq($app, Request::create('/api/logistics/scan/context', 'GET'), $staffToken);
    check($res->getStatusCode() === 200, '[Mobile Scanner] GET /api/logistics/scan/context', 'Mobile scanner context endpoint loads', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/notifications', 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Notifications] GET /api/logistics/rider/notifications', 'Rider push notifications inbox loads', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/notifications/read-all', 'PATCH'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Notifications] PATCH /api/logistics/rider/notifications/read-all', 'Mark all notifications read button works', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/messages', 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Chat] GET /api/logistics/rider/messages', 'Rider messages inbox loads', $res);

    $res = handleApiReq($app, Request::create('/api/logistics/rider/conversations', 'GET'), $bearerToken);
    check($res->getStatusCode() === 200, '[Mobile Chat] GET /api/logistics/rider/conversations', 'Rider support conversations load', $res);

} catch (\Throwable $e) {
    echo "\n[ERROR EXCEPTION]: " . $e->getMessage() . "\n";
    echo "At " . $e->getFile() . ":" . $e->getLine() . "\n";
    $failed++;
} finally {
    DB::rollBack();
    echo "\n[INFO] Database transaction rolled back cleanly; zero test data persisted.\n\n";
}

echo "====================================================================\n";
echo "LOGISTICS & MOBILE AUDIT SUMMARY: Total " . ($passed + $failed) . " checks: $passed Passed, $failed Failed.\n";
echo "====================================================================\n";

exit($failed > 0 ? 1 : 0);
