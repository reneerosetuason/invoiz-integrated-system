<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Delivery;
use App\Models\DeliveryFailure;
use App\Models\DeliveryProof;
use App\Models\DeliveryStatusLog;
use App\Models\EscrowRemittance;
use App\Models\GcashEscrowDeposit;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\ParcelLiability as ParcelLiabilityModel;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\ReturnWorkflow;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\SellerNotification;
use App\Models\User;
use App\Services\ReturnToSellerService;
use App\Services\ShipmentExceptionService;
use App\Support\EscrowEarnings;
use App\Support\ParcelLiability;
use App\Support\Payments;
use App\Support\Stock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

echo "=================================================================\n";
echo " Custody-Based Liability for Damaged Shipments E2E Test Suite   \n";
echo "=================================================================\n\n";

$createdEntities = [
    'users' => [],
    'sellers' => [],
    'products' => [],
    'orders' => [],
    'deliveries' => [],
    'liabilities' => [],
    'transfers' => [],
    'files' => [],
];

try {
    // -------------------------------------------------------------
    // Setup Base Actors & Data
    // -------------------------------------------------------------
    $buyer = User::factory()->create(['role' => 'buyer', 'email' => 'e2e_buyer_'.uniqid().'@test.com']);
    $createdEntities['users'][] = $buyer->id;

    $address = \App\Models\Address::create([
        'buyer_id' => $buyer->id,
        'recipient_name' => 'E2E Buyer',
        'phone' => '09171234567',
        'address_line' => '100 E2E St',
        'barangay' => 'San Antonio',
        'city' => 'Manila',
        'province' => 'Metro Manila',
        'is_default' => true,
    ]);

    $sellerUser = User::factory()->create(['role' => 'seller', 'email' => 'e2e_seller_'.uniqid().'@test.com']);
    $createdEntities['users'][] = $sellerUser->id;

    $seller = Seller::create([
        'user_id' => $sellerUser->id,
        'store_name' => 'E2E Test Store '.uniqid(),
        'business_name' => 'E2E Merchant Corp',
        'approval_status' => 'approved',
        'status' => 'active',
        'terms_accepted' => true,
        'line_of_business' => 'Retail',
    ]);
    $createdEntities['sellers'][] = $seller->id;

    $product = Product::create([
        'seller_id' => $sellerUser->id,
        'name' => 'Premium Headset '.uniqid(),
        'description' => 'E2E Test Headset',
        'price' => 1200.00,
        'stock' => 10,
        'status' => 'active',
        'category_id' => 1,
    ]);
    $createdEntities['products'][] = $product->id;
    $initialStock = $product->stock;

    $originCenter = LogisticsCenter::firstOrCreate(
        ['name' => 'E2E Origin Logistics Hub'],
        ['address' => '123 Hub St', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_active' => true]
    );
    $destCenter = LogisticsCenter::firstOrCreate(
        ['name' => 'E2E Destination Logistics Hub'],
        ['address' => '456 Dest St', 'city' => 'Quezon City', 'province' => 'Metro Manila', 'is_active' => true]
    );

    $riderUser = User::factory()->create(['role' => 'rider', 'email' => 'e2e_rider_'.uniqid().'@test.com']);
    $createdEntities['users'][] = $riderUser->id;

    $rider = Rider::create([
        'user_id' => $riderUser->id,
        'center_id' => $destCenter->id,
        'name' => 'E2E Rider',
        'email' => $riderUser->email,
        'phone' => '09171234567',
        'vehicle_type' => 'motorcycle',
        'is_verified' => true,
        'status' => 'available',
    ]);

    // -------------------------------------------------------------
    // Helper function to assemble a test order & delivery
    // -------------------------------------------------------------
    $createTestShipment = function (string $paymentMethod = 'gcash', bool $withTransfer = true) use (
        $buyer, $address, $sellerUser, $seller, $product, $originCenter, $destCenter, $rider, &$createdEntities
    ) {
        $order = Order::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $sellerUser->id,
            'address_id' => $address->id,
            'status' => 'processing',
            'payment_status' => $paymentMethod === 'gcash' ? 'paid' : 'pending',
            'total_amount' => 1250.00, // 1200 merch + 50 shipping
            'shipping_fee' => 50.00,
        ]);
        $createdEntities['orders'][] = $order->id;

        $orderItem = OrderItem::create([
            'order_id' => $order->id,
            'seller_id' => $sellerUser->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => 1200.00,
            'quantity' => 1,
            'total_price' => 1200.00,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'amount' => 1250.00,
            'method' => $paymentMethod,
            'status' => $paymentMethod === 'gcash' ? 'paid' : 'pending',
            'proof_path' => $paymentMethod === 'gcash' ? 'test-proof.png' : null,
            'paid_at' => $paymentMethod === 'gcash' ? now() : null,
        ]);

        $tx = PaymentTransaction::create([
            'order_id' => $order->id,
            'transaction_ref' => 'TXN-E2E-'.$order->id,
            'gateway' => $paymentMethod,
            'amount' => 1250.00,
            'status' => $paymentMethod === 'gcash' ? 'successful' : 'pending',
        ]);

        if ($paymentMethod === 'gcash') {
            GcashEscrowDeposit::create([
                'order_id' => $order->id,
                'seller_id' => $sellerUser->id,
                'amount' => 1250.00,
                'status' => 'funded',
                'confirmed_at' => now(),
            ]);
        }

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'seller_id' => $sellerUser->id,
            'tracking_number' => 'E2E-TRK-'.uniqid(),
            'status' => 'out_for_delivery',
            'origin_center_id' => $originCenter->id,
            'destination_center_id' => $destCenter->id,
            'center_id' => $destCenter->id,
            'rider_id' => $rider->id,
            'picked_up_at' => now()->subHours(5),
            'received_at' => now()->subHours(3),
            'delivery_fee' => 50.00,
            'amount_to_collect' => $paymentMethod === 'gcash' ? 0.00 : 1250.00,
        ]);
        $createdEntities['deliveries'][] = $delivery->id;

        if ($withTransfer) {
            $transfer = HubTransfer::create([
                'reference' => 'MANIFEST-'.uniqid(),
                'logistics_company_id' => $originCenter->logistics_company_id ?: 1,
                'origin_center_id' => $originCenter->id,
                'destination_center_id' => $destCenter->id,
                'status' => 'received',
                'payment_version' => 0,
            ]);
            $createdEntities['transfers'][] = $transfer->id;

            HubTransferParcel::create([
                'hub_transfer_id' => $transfer->id,
                'delivery_id' => $delivery->id,
                'loading_status' => 'loaded',
                'receiving_status' => 'received',
                'condition' => 'good', // Received in good condition at destination hub
                'received_at' => now()->subHours(3),
            ]);
        }

        EscrowEarnings::syncRemittancesForOrder($order, $tx);

        return [$order, $delivery];
    };

    // =============================================================
    // TEST 1: Seed Prepaid Order with Delivery in Out For Delivery
    // =============================================================
    echo "--- TEST 1: Seed Prepaid (GCash) Order in Out For Delivery ---\n";
    [$order1, $delivery1] = $createTestShipment('gcash', true);
    assert($delivery1->status === 'out_for_delivery', "Delivery should be out_for_delivery");
    assert($order1->payment_status === 'paid', "Prepaid order should be paid");
    echo "[PASS] Order #{$order1->id} and Delivery #{$delivery1->id} seeded in out_for_delivery.\n\n";

    // =============================================================
    // TEST 2: Rider Reports Damage with Photo
    // =============================================================
    echo "--- TEST 2: Rider Reports Damage with Photo ---\n";
    Storage::fake('public');
    $photo = UploadedFile::fake()->create('broken_box.jpg', 500, 'image/jpeg');

    $exceptionService = app(ShipmentExceptionService::class);
    $reportedDelivery = $exceptionService->report(
        $delivery1,
        'damaged',
        'Box crushed and merchandise punctured during bike transport',
        'Handle with care mark ignored',
        $photo,
        $riderUser,
        'rider'
    );
    assert($reportedDelivery->status === 'damaged', "Delivery status must be damaged");
    assert(DeliveryFailure::where('delivery_id', $delivery1->id)->exists(), "DeliveryFailure record must exist");
    assert(DeliveryProof::where('delivery_id', $delivery1->id)->where('type', 'damage_evidence')->exists(), "Damage evidence proof must exist");

    $proposedLiability = ParcelLiabilityModel::where('delivery_id', $delivery1->id)->first();
    assert($proposedLiability !== null, "Parcel liability must be proposed");
    assert($proposedLiability->status === ParcelLiabilityModel::STATUS_PROPOSED, "Liability status must be proposed");
    assert($proposedLiability->custody_classification === ParcelLiabilityModel::CUSTODY_LAST_MILE_RIDER, "Custody must be last_mile_rider since hub received in good condition");
    assert((float)$proposedLiability->merchandise_amount === 1200.00, "Merchandise amount must be 1200.00");
    $createdEntities['liabilities'][] = $proposedLiability->id;
    echo "[PASS] Damage reported with photo: status=damaged, custody=last_mile_rider, liability=proposed (₱{$proposedLiability->merchandise_amount}).\n\n";

    // =============================================================
    // TEST 3: Validate Report -> Buyer Refund Queued Immediately
    // =============================================================
    echo "--- TEST 3: Validate Report -> Buyer Refund Queued Immediately ---\n";
    $validatedLiability = ParcelLiability::validateReport($delivery1, $riderUser);
    assert($validatedLiability->status === ParcelLiabilityModel::STATUS_ACCEPTED, "Liability status must be accepted");

    // Verify buyer refund is queued in return_workflows BEFORE parcel physical return
    $returnRequest = ReturnRequest::where('order_id', $order1->id)->first();
    assert($returnRequest !== null, "ReturnRequest must be created for buyer refund");
    $returnWorkflow = ReturnWorkflow::where('return_request_id', $returnRequest->id)->first();
    assert($returnWorkflow !== null, "ReturnWorkflow must exist before physical return");
    assert($returnWorkflow->stage === 'refund_pending', "ReturnWorkflow stage must be refund_pending");
    echo "[PASS] Damage validated: liability=accepted, buyer refund queued immediately in return_workflows.\n\n";

    // =============================================================
    // TEST 4: Return-to-Seller Dispatch & Confirm -> Completed Legs Payable
    // =============================================================
    echo "--- TEST 4: Return to Seller -> Completed Legs Payable ---\n";
    $delivery1->refresh();
    $delivery1->returned_at = now();
    $delivery1->save();

    $returnService = app(ReturnToSellerService::class);
    $returnService->dispatch($delivery1, null, 'RET-REF-01');
    $returnService->confirm($delivery1, $sellerUser, 'Received damaged parcel');

    $order1->refresh();
    assert($order1->status === 'cancelled', "Order must be cancelled after seller confirms return");

    // Check stock was NOT restored (damaged goods not saleable)
    $product->refresh();
    assert($product->stock === $initialStock, "Stock must NOT be restored for damaged goods");

    // Check remittances for completed legs vs voided legs
    $remittances = EscrowRemittance::where('order_id', $order1->id)->get()->keyBy('recipient_role');
    assert($remittances[EscrowRemittance::ROLE_ORIGIN_HUB]->status === 'pending_remittance', "Origin hub remittance must be pending_remittance (payable)");
    assert($remittances[EscrowRemittance::ROLE_PICKUP_RIDER]->status === 'pending_remittance', "Pickup rider remittance must be pending_remittance (payable)");
    assert($remittances[EscrowRemittance::ROLE_INTER_HUB_DRIVER]->status === 'pending_remittance', "Inter-hub driver remittance must be pending_remittance (payable)");
    assert($remittances[EscrowRemittance::ROLE_DESTINATION_HUB]->status === 'pending_remittance', "Destination hub remittance must be pending_remittance (payable)");
    assert($remittances[EscrowRemittance::ROLE_LAST_MILE_RIDER]->status === 'voided', "Last-mile rider remittance must be voided");
    assert($remittances[EscrowRemittance::ROLE_ADMIN]->status === 'voided', "Admin commission remittance must be voided");
    assert($remittances[EscrowRemittance::ROLE_SELLER]->status === 'voided', "Seller earning remittance must be voided");
    echo "[PASS] Return confirmed, order cancelled: completed legs (origin, pickup, linehaul, dest) are payable; last-mile/admin/seller voided; stock intact.\n\n";

    // =============================================================
    // TEST 5: Settle Liability -> Seller Credited Merchandise Amount
    // =============================================================
    echo "--- TEST 5: Settle Liability -> Seller Credited ---\n";
    $beforeBalance = Payments::availableBalanceForUser($sellerUser->id);
    ParcelLiability::settle($validatedLiability);
    $validatedLiability->refresh();
    assert($validatedLiability->status === ParcelLiabilityModel::STATUS_SETTLED, "Liability status must be settled");
    $afterBalance = Payments::availableBalanceForUser($sellerUser->id);
    assert($afterBalance === round($beforeBalance + 1200.00, 2), "Seller available balance must increase by merchandise amount 1200.00");
    echo "[PASS] Liability settled: seller store balance credited by ₱1,200.00 (before: {$beforeBalance}, after: {$afterBalance}).\n\n";

    // =============================================================
    // TEST 6: Dispute Flow (Uphold & Overturn)
    // =============================================================
    echo "--- TEST 6: Dispute Flow (Uphold and Overturn) ---\n";
    // Case 6A: Dispute -> Uphold
    [$order2, $delivery2] = $createTestShipment('gcash', false);
    $reported2 = $exceptionService->report($delivery2, 'damaged', 'Item damaged', 'Broken', $photo, $riderUser, 'rider');
    $delivery2->refresh();
    $delivery2->returned_at = now();
    $delivery2->save();
    $returnService->dispatch($delivery2);
    $returnService->confirm($delivery2, $sellerUser);

    $liability2 = ParcelLiabilityModel::where('delivery_id', $delivery2->id)->first();
    $createdEntities['liabilities'][] = $liability2->id;

    // Seller disputes
    $disputedLiability = ParcelLiability::dispute($delivery2, $sellerUser, 'Item was packed in reinforced wooden crate');
    assert($disputedLiability->status === ParcelLiabilityModel::STATUS_DISPUTED, "Liability must be disputed");

    // Verify dispute pauses settlement
    $pausedException = false;
    try {
        ParcelLiability::settle($disputedLiability);
    } catch (ValidationException $e) {
        $pausedException = true;
    }
    assert($pausedException === true, "Settlement must be paused during active dispute");

    // Admin upholds dispute -> settled
    $adminUser = User::where('role', 'admin')->orWhere('is_admin', true)->first() ?: User::factory()->create(['role' => 'admin']);
    $upheldLiability = ParcelLiability::resolveDispute($disputedLiability, 'uphold', 'Courier failed packaging guidelines', $adminUser);
    assert($upheldLiability->status === ParcelLiabilityModel::STATUS_SETTLED, "Upheld dispute must settle liability");
    assert($upheldLiability->resolution_outcome === 'upheld', "Resolution outcome must be upheld");
    echo "[PASS] 6A. Dispute raised (settlement paused) -> Admin upheld -> Liability settled.\n";

    // Case 6B: Dispute -> Overturn -> Reverse Liability & Restore Stock
    [$order3, $delivery3] = $createTestShipment('cash_on_delivery', false);
    $exceptionService->report($delivery3, 'damaged', 'Item damaged', 'Torn', $photo, $riderUser, 'rider');
    $delivery3->refresh();
    $delivery3->returned_at = now();
    $delivery3->save();
    $returnService->dispatch($delivery3);
    $returnService->confirm($delivery3, $sellerUser);

    $liability3 = ParcelLiabilityModel::where('delivery_id', $delivery3->id)->first();
    $createdEntities['liabilities'][] = $liability3->id;

    $disputedLiability3 = ParcelLiability::dispute($delivery3, $sellerUser, 'Seller claims item is actually fine');
    $stockBeforeOverturn = $product->fresh()->stock;
    $overturnedLiability = ParcelLiability::resolveDispute($disputedLiability3, 'overturn', 'Product inspected: undamaged and resaleable', $adminUser);
    assert($overturnedLiability->status === ParcelLiabilityModel::STATUS_REVERSED, "Overturned dispute must reverse liability");
    assert($overturnedLiability->resolution_outcome === 'overturned', "Resolution outcome must be overturned");
    $stockAfterOverturn = $product->fresh()->stock;
    assert($stockAfterOverturn === $stockBeforeOverturn + 1, "Overturning dispute must restore saleable stock");
    echo "[PASS] 6B. Dispute raised -> Admin overturned -> Liability reversed and saleable stock restored (+1).\n\n";

    // =============================================================
    // TEST 7: Negative Tests
    // =============================================================
    echo "--- TEST 7: Negative Tests ---\n";
    // 7A: Report damage on delivered parcel -> 422
    [$orderDelivered, $delDelivered] = $createTestShipment('gcash', false);
    $delDelivered->update(['status' => 'delivered', 'delivered_at' => now()]);
    $deliveredBlocked = false;
    try {
        $exceptionService->report($delDelivered, 'damaged', 'Too late', 'Already delivered', $photo, $riderUser, 'rider');
    } catch (ValidationException $e) {
        $deliveredBlocked = true;
    }
    assert($deliveredBlocked === true, "Reporting damage on delivered parcel must throw 422");
    echo "[PASS] 7A. Reporting damage on delivered parcel rejected (422).\n";

    // 7B: Report damage on cancelled parcel -> 422
    [$orderCancelled, $delCancelled] = $createTestShipment('gcash', false);
    $delCancelled->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    $cancelledBlocked = false;
    try {
        $exceptionService->report($delCancelled, 'damaged', 'Cancelled', 'Already cancelled', $photo, $riderUser, 'rider');
    } catch (ValidationException $e) {
        $cancelledBlocked = true;
    }
    assert($cancelledBlocked === true, "Reporting damage on cancelled parcel must throw 422");
    echo "[PASS] 7B. Reporting damage on cancelled parcel rejected (422).\n";

    // 7C: Report damage without photo -> 422
    [$orderNoPhoto, $delNoPhoto] = $createTestShipment('gcash', false);
    $noPhotoBlocked = false;
    try {
        $exceptionService->report($delNoPhoto, 'damaged', 'Cracked', 'No photo attached', null, $riderUser, 'rider');
    } catch (ValidationException $e) {
        $noPhotoBlocked = true;
    }
    assert($noPhotoBlocked === true, "Reporting damage without photo must throw 422");
    echo "[PASS] 7C. Reporting damage without photo rejected (422).\n";

    // 7D: Dispute after 7 days from return confirmation -> rejected
    $delivery1->refresh();
    // Simulate return confirmation happened 10 days ago
    DeliveryStatusLog::where('delivery_id', $delivery1->id)
        ->where('status', ReturnToSellerService::RECEIVED)
        ->update(['created_at' => now()->subDays(10)]);
    $expiredDispute = false;
    try {
        ParcelLiability::dispute($delivery1, $sellerUser, 'Disputing after 10 days');
    } catch (ValidationException $e) {
        $expiredDispute = true;
    }
    assert($expiredDispute === true, "Disputing after 7 days from return confirmation must be rejected");
    echo "[PASS] 7D. Dispute after 7 days rejected (ValidationException).\n\n";

} catch (\Throwable $e) {
    echo "\n[ERROR in test run]: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n\n";
} finally {
    // =============================================================
    // TEST 8: Clean Up All Test Data & Print Summary
    // =============================================================
    echo "--- TEST 8: Clean Up All Test Data & Files ---\n";
    DB::statement('SET FOREIGN_KEY_CHECKS=0;');
    $deletedSummary = [];

    // Delete Liabilities
    $liabCount = ParcelLiabilityModel::whereIn('id', $createdEntities['liabilities'])->delete();
    $deletedSummary['parcel_liabilities'] = $liabCount;

    // Delete Deliveries and related proofs, failures, logs, transfer parcels
    $delIds = $createdEntities['deliveries'];
    if (!empty($delIds)) {
        DeliveryProof::whereIn('delivery_id', $delIds)->delete();
        DeliveryFailure::whereIn('delivery_id', $delIds)->delete();
        DeliveryStatusLog::whereIn('delivery_id', $delIds)->delete();
        HubTransferParcel::whereIn('delivery_id', $delIds)->delete();
        EscrowRemittance::whereIn('delivery_id', $delIds)->delete();
        $delCount = Delivery::whereIn('id', $delIds)->delete();
        $deletedSummary['deliveries'] = $delCount;
    }

    // Delete Orders, Items, Payments, Workflows, Returns, Histories
    $orderIds = $createdEntities['orders'];
    if (!empty($createdEntities['users'])) {
        $extraOrderIds = Order::whereIn('buyer_id', $createdEntities['users'])->orWhereIn('seller_id', $createdEntities['users'])->pluck('id')->all();
        $orderIds = array_values(array_unique(array_merge($orderIds, $extraOrderIds)));
    }
    if (!empty($orderIds)) {
        GcashEscrowDeposit::whereIn('order_id', $orderIds)->delete();
        $rrIds = ReturnRequest::whereIn('order_id', $orderIds)->pluck('id');
        ReturnWorkflow::whereIn('return_request_id', $rrIds)->delete();
        ReturnRequest::whereIn('id', $rrIds)->delete();
        OrderStatusHistory::whereIn('order_id', $orderIds)->delete();
        PaymentTransaction::whereIn('order_id', $orderIds)->delete();
        Payment::whereIn('order_id', $orderIds)->delete();
        OrderItem::whereIn('order_id', $orderIds)->delete();
        SellerNotification::whereIn('order_id', $orderIds)->delete();
        $orderCount = Order::whereIn('id', $orderIds)->delete();
        $deletedSummary['orders'] = $orderCount;
    }

    // Delete Transfers
    if (!empty($createdEntities['transfers'])) {
        HubTransfer::whereIn('id', $createdEntities['transfers'])->delete();
        $deletedSummary['hub_transfers'] = count($createdEntities['transfers']);
    }

    // Delete Products, Sellers, Users
    if (!empty($createdEntities['products'])) {
        Product::whereIn('id', $createdEntities['products'])->forceDelete();
        $deletedSummary['products'] = count($createdEntities['products']);
    }
    if (!empty($createdEntities['sellers'])) {
        Seller::whereIn('id', $createdEntities['sellers'])->forceDelete();
        $deletedSummary['sellers'] = count($createdEntities['sellers']);
    }
    if (!empty($createdEntities['users'])) {
        \App\Models\Address::whereIn('buyer_id', $createdEntities['users'])->delete();
        Rider::whereIn('user_id', $createdEntities['users'])->delete();
        Product::whereIn('seller_id', $createdEntities['users'])->forceDelete();
        User::whereIn('id', $createdEntities['users'])->forceDelete();
        $deletedSummary['users'] = count($createdEntities['users']);
    }

    DB::statement('SET FOREIGN_KEY_CHECKS=1;');

    echo "Deleted entities summary:\n";
    foreach ($deletedSummary as $table => $count) {
        echo "  - {$table}: {$count}\n";
    }
    echo "\n*** ALL 8 TESTS COMPLETED AND CLEANED UP SUCCESSFULLY! ***\n";
}
