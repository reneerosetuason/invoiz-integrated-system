<?php

namespace Tests\Feature;

use App\Models\CommissionRate;
use App\Models\Delivery;
use App\Models\EscrowRemittance;
use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderVoucher;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\Seller;
use App\Models\User;
use App\Models\Voucher;
use App\Services\FulfillmentWorkflow;
use App\Services\HubTransferService;
use App\Support\EscrowEarnings;
use App\Support\LogisticsAccess;
use App\Support\Payments;
use App\Support\Remittances;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Allocation, escrow and custody reconciliation on the isolated sandbox.
 *
 * Uses DatabaseTransactions (never RefreshDatabase): the sandbox holds the
 * only approved dataset and migrate:fresh is not allowed there.
 */
class LogisticsAllocationReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function token(string $prefix): string
    {
        $this->seq++;

        return $prefix.'-'.$this->seq.'-'.substr(md5($prefix.microtime(true).$this->seq), 0, 8);
    }

    private function company(string $label): LogisticsCompany
    {
        return LogisticsCompany::create(['name' => $label.' '.$this->token('co'), 'status' => 'active']);
    }

    private function center(LogisticsCompany $company, string $label): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $label.' '.$this->token('hub'),
            'address' => '1 Allocation Road',
            'city' => 'Test City',
            'is_active' => true,
            'logistics_company_id' => $company->id,
        ]);
    }

    private function rider(?int $centerId = null): Rider
    {
        $token = $this->token('rider');
        $user = User::create([
            'name' => 'Allocation Rider',
            'email' => $token.'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $rider = Rider::create([
            'user_id' => $user->id,
            'name' => 'Allocation Rider',
            'email' => $token.'@test.local',
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'center_id' => $centerId,
        ]);

        return $rider->fresh();
    }

    /** @return array{0: Order, 1: int} */
    private function order(int $quantity = 2, float $unitPrice = 60.0): array
    {
        $seller = Seller::factory()->create();
        $token = $this->token('buyer');
        $buyer = User::create([
            'name' => 'Allocation Buyer',
            'email' => $token.'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
        ]);

        $merch = round($quantity * $unitPrice, 2);
        $order = Order::create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'status' => 'delivered',
            'payment_status' => 'pending',
            'total' => $merch,
            'total_amount' => $merch,
            'sub_total' => $merch,
            'shipping_fee' => Order::SHIPPING_FEE_PER_SELLER,
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => null,
            'name' => 'Allocation Product '.$this->token('p'),
            'slug' => $this->token('allocation-product'),
            'status' => 'active',
            'price' => $unitPrice,
            'stock' => 100,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $merch,
        ]);

        return [$order->fresh(), (int) $seller->id];
    }

    private function delivery(Order $order, int $sellerId, array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'order_id' => $order->id,
            'seller_id' => $sellerId,
            'status' => 'delivered',
            'tracking_number' => $this->token('TRK'),
            'sender_name' => 'Allocation Sender',
            'recipient_name' => 'Allocation Recipient',
            'sender_address' => 'Sender Road',
            'recipient_address' => 'Recipient Road',
            'payment_method' => 'cash_on_delivery',
            'weight' => 2,
            'received_at' => now(),
            'delivered_at' => now(),
        ], $extra));
    }

    /**
     * Run a real fleet transfer through dispatch -> arrive -> receive -> complete.
     *
     * @return array{0: HubTransfer, 1: HubTransportDriver}
     */
    private function runTransfer(LogisticsCompany $company, LogisticsCenter $origin, LogisticsCenter $destination, Delivery $delivery): array
    {
        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => strtoupper($this->token('PLT')),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 10,
            'capacity_kg' => 500,
            'status' => 'assigned',
        ]);
        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck->id,
            'name' => 'Transport Driver '.$this->token('d'),
            'email' => $this->token('driver').'@test.local',
            'password' => Hash::make('secret123'),
            'status' => 'assigned',
            'is_online' => true,
        ]);
        $transfer = HubTransfer::create([
            'reference' => $this->token('HT'),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'ready_for_dispatch',
        ]);
        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'pending',
        ]);

        HubTransferService::dispatch($transfer);
        HubTransferService::arrive($transfer->fresh());
        HubTransferService::receiveParcel($transfer->fresh(), $delivery->id, 'good', null);
        HubTransferService::complete($transfer->fresh());

        return [$transfer->fresh(), $driver->fresh()];
    }

    public function test_same_hub_allocation_has_no_inter_hub_or_destination_hub_fee(): void
    {
        [$order, $sellerId] = $this->order(2, 60.0);
        $company = $this->company('Same Hub');
        $hub = $this->center($company, 'Hub A');
        $rider = $this->rider($hub->id);
        $delivery = $this->delivery($order, $sellerId, [
            'center_id' => $hub->id,
            'destination_center_id' => $hub->id,
            'rider_id' => $rider->id,
            'delivery_fee' => null,
        ]);

        $calc = EscrowEarnings::calculateForSeller($order, $sellerId, $delivery);

        $this->assertTrue($calc['has_group']);
        $this->assertSame(2, $calc['item_quantity']);
        $this->assertEqualsWithDelta(30.0, $calc['origin_hub_fee'], 0.001);
        $this->assertEqualsWithDelta(60.0, $calc['pickup_rider_fee'], 0.001);
        $this->assertEqualsWithDelta(0.0, $calc['inter_hub_driver_fee'], 0.001);
        $this->assertEqualsWithDelta(0.0, $calc['destination_hub_fee'], 0.001);
        $this->assertEqualsWithDelta(50.0, $calc['last_mile_rider_fee'], 0.001);
        $this->assertEqualsWithDelta(12.0, $calc['admin_commission'], 0.001);
        $this->assertEqualsWithDelta(170.0, $calc['buyer_total_payable'], 0.001);
        $this->assertEqualsWithDelta(18.0, $calc['seller_earning'], 0.001);
        $this->assertEqualsWithDelta(0.0, $calc['seller_deficit'], 0.001);
        $this->assertTrue($calc['conserved']);
    }

    public function test_one_completed_transfer_charges_inter_hub_and_destination_fees_once(): void
    {
        [$order, $sellerId] = $this->order(2, 60.0);
        $company = $this->company('One Transfer');
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $rider = $this->rider($destination->id);
        $delivery = $this->delivery($order, $sellerId, [
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'rider_id' => $rider->id,
            'delivery_fee' => null,
        ]);

        $this->runTransfer($company, $origin, $destination, $delivery);
        $delivery = $delivery->fresh();

        $calc = EscrowEarnings::calculateForSeller($order->fresh(), $sellerId, $delivery);

        $this->assertEqualsWithDelta(30.0, $calc['origin_hub_fee'], 0.001);
        $this->assertEqualsWithDelta(20.0, $calc['inter_hub_driver_fee'], 0.001);
        $this->assertEqualsWithDelta(30.0, $calc['destination_hub_fee'], 0.001);
        $this->assertEqualsWithDelta(60.0, $calc['pickup_rider_fee'], 0.001);
        $this->assertEqualsWithDelta(50.0, $calc['last_mile_rider_fee'], 0.001);
        // The buyer paid 120 merchandise + 50 shipping; the stated recipient
        // fees total 202, so the shortfall is explicit, never hidden.
        $this->assertEqualsWithDelta(32.0, $calc['seller_deficit'], 0.001);
        $this->assertEqualsWithDelta(0.0, $calc['seller_earning'], 0.001);
        $this->assertTrue($calc['conserved']);
    }

    public function test_multi_leg_route_pays_the_inter_hub_fee_once_and_keeps_first_leg_driver(): void
    {
        [$order, $sellerId] = $this->order(2, 60.0);
        $company = $this->company('Multi Leg');
        $hubA = $this->center($company, 'Hub A');
        $hubB = $this->center($company, 'Hub B');
        $hubC = $this->center($company, 'Hub C');
        $rider = $this->rider($hubC->id);
        $delivery = $this->delivery($order, $sellerId, [
            'center_id' => $hubA->id,
            'destination_center_id' => $hubC->id,
            'rider_id' => $rider->id,
            'delivery_fee' => null,
        ]);

        [$firstLeg, $firstDriver] = $this->runTransfer($company, $hubA, $hubB, $delivery);
        [$secondLeg, $secondDriver] = $this->runTransfer($company, $hubB, $hubC, $delivery->fresh());

        $earnings = HubTransferEarning::where('delivery_id', $delivery->id)->get();
        $this->assertCount(1, $earnings);
        $this->assertEqualsWithDelta(20.0, (float) $earnings->first()->amount, 0.001);
        $this->assertSame($firstDriver->id, (int) $earnings->first()->hub_transport_driver_id);
        $this->assertSame($firstLeg->id, (int) $earnings->first()->hub_transfer_id);
        $this->assertNotSame($secondDriver->id, (int) $earnings->first()->hub_transport_driver_id);

        $calc = EscrowEarnings::calculateForSeller($order->fresh(), $sellerId, $delivery->fresh());
        $this->assertSame($hubA->id, $calc['origin_center_id']);
        $this->assertSame($hubC->id, $calc['destination_center_id']);
        $this->assertEqualsWithDelta(20.0, $calc['inter_hub_driver_fee'], 0.001);
        $this->assertEqualsWithDelta(30.0, $calc['destination_hub_fee'], 0.001);
    }

    public function test_repeated_receipts_and_credits_do_not_duplicate_earnings(): void
    {
        [$order, $sellerId] = $this->order(2, 60.0);
        [$secondOrder, $secondSellerId] = $this->order(1, 60.0);
        $company = $this->company('Idempotent Receipt');
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $delivery = $this->delivery($order, $sellerId, [
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'sorting_center_handoff_rider_id' => $this->rider($origin->id)->id,
            'parcel_status' => 'received',
        ]);
        $other = $this->delivery($secondOrder, $secondSellerId, [
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
        ]);

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => strtoupper($this->token('PLT')),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 10,
            'capacity_kg' => 500,
            'status' => 'assigned',
        ]);
        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck->id,
            'name' => 'Repeat Driver',
            'email' => $this->token('repeat').'@test.local',
            'password' => Hash::make('secret123'),
            'status' => 'assigned',
            'is_online' => true,
        ]);
        $transfer = HubTransfer::create([
            'reference' => $this->token('HT-REPEAT'),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'ready_for_dispatch',
        ]);
        foreach ([$delivery, $other] as $manifestParcel) {
            HubTransferParcel::create([
                'hub_transfer_id' => $transfer->id,
                'delivery_id' => $manifestParcel->id,
                'loading_status' => 'loaded',
                'receiving_status' => 'pending',
            ]);
        }
        HubTransferService::dispatch($transfer);
        HubTransferService::arrive($transfer->fresh());

        $first = HubTransferService::receiveParcel($transfer->fresh(), $delivery->id, 'good', null);
        $this->assertFalse($first['duplicate']);

        // The transfer is still receiving its second parcel, so the same
        // manifest line can be posted again and must stay a no-op.
        $repeat = HubTransferService::receiveParcel($transfer->fresh(), $delivery->id, 'good', null);
        $this->assertTrue($repeat['duplicate']);

        HubTransferService::receiveParcel($transfer->fresh(), $other->id, 'good', null);
        HubTransferService::complete($transfer->fresh());

        $this->assertSame(1, HubTransferEarning::where('delivery_id', $delivery->id)->count());
        $this->assertSame(1, HubTransferEarning::where('delivery_id', $other->id)->count());

        // Pickup credit belongs to parcels received from the pickup rider
        // before any transfer leg exists. Retrying the same receipt is free.
        [$pickupOrder, $pickupSellerId] = $this->order(2, 60.0);
        $pickupRider = $this->rider($origin->id);
        $pickupDelivery = $this->delivery($pickupOrder, $pickupSellerId, [
            'center_id' => $origin->id,
            'destination_center_id' => $origin->id,
            'sorting_center_handoff_rider_id' => $pickupRider->id,
            'parcel_status' => 'received',
        ]);
        EscrowEarnings::creditPickupRiderOnHubReceipt($pickupDelivery->fresh());
        EscrowEarnings::creditPickupRiderOnHubReceipt($pickupDelivery->fresh());

        $pickup = RiderEarning::where('delivery_id', $pickupDelivery->id)->where('type', 'pickup')->get();
        $this->assertCount(1, $pickup);
        $this->assertEqualsWithDelta(60.0, (float) $pickup->first()->amount, 0.001);
    }

    public function test_configured_commission_and_last_mile_fee_drive_the_allocation(): void
    {
        CommissionRate::updateOrCreate(['category_id' => null], ['rate' => 7.5]);

        [$order, $sellerId] = $this->order(2, 60.0);
        $company = $this->company('Configured');
        $hub = $this->center($company, 'Hub');
        $rider = $this->rider($hub->id);
        $delivery = $this->delivery($order, $sellerId, [
            'center_id' => $hub->id,
            'destination_center_id' => $hub->id,
            'rider_id' => $rider->id,
            'delivery_fee' => 55,
        ]);

        $calc = EscrowEarnings::calculateForSeller($order->fresh(), $sellerId, $delivery);

        $this->assertEqualsWithDelta(7.5, $calc['commission_rate'], 0.001);
        $this->assertEqualsWithDelta(9.0, $calc['admin_commission'], 0.001);
        $this->assertEqualsWithDelta(55.0, $calc['last_mile_rider_fee'], 0.001);
        $this->assertTrue($calc['conserved']);
    }

    public function test_free_shipping_conservation_reports_the_deficit_explicitly(): void
    {
        $order = new Order([
            'total' => 120,
            'total_amount' => 120,
            'sub_total' => 120,
            'shipping_fee' => Order::SHIPPING_FEE_PER_SELLER,
        ]);
        $order->setRelation('items', collect([
            new OrderItem(['seller_id' => 7, 'quantity' => 2, 'unit_price' => 60, 'product_id' => 1]),
        ]));
        $voucher = new Voucher(['discount_type' => 'free_shipping', 'type' => 'free_shipping']);
        $applied = new OrderVoucher(['discount_amount' => 50]);
        $applied->setRelation('voucher', $voucher);
        $order->setRelation('orderVouchers', collect([$applied]));
        $delivery = new Delivery(['center_id' => 3, 'destination_center_id' => 3]);

        $calc = EscrowEarnings::calculateForSeller($order, 7, $delivery);

        $this->assertEqualsWithDelta(50.0, $calc['shipping_waived'], 0.001);
        $this->assertEqualsWithDelta(0.0, $calc['buyer_shipping_paid'], 0.001);
        $this->assertEqualsWithDelta(120.0, $calc['buyer_total_payable'], 0.001);
        $this->assertEqualsWithDelta(32.0, $calc['seller_deficit'], 0.001);
        $this->assertEqualsWithDelta(0.0, $calc['seller_earning'], 0.001);
        $this->assertTrue($calc['conserved']);
        $this->assertEqualsWithDelta(0.0, $calc['allocation_difference'], 0.001);
        $this->assertEqualsWithDelta(0.0, $calc['voucher_difference'], 0.001);
    }

    public function test_settlement_books_fee_details_and_repeated_settlement_adds_no_remittances(): void
    {
        [$order, $sellerId] = $this->order(2, 60.0);
        $company = $this->company('Settlement');
        $hub = $this->center($company, 'Hub');
        $rider = $this->rider($hub->id);
        $delivery = $this->delivery($order, $sellerId, [
            'center_id' => $hub->id,
            'destination_center_id' => $hub->id,
            'rider_id' => $rider->id,
            'delivery_fee' => null,
        ]);

        Payments::settleForDelivery($order->fresh());
        $tx = PaymentTransaction::where('order_id', $order->id)->firstOrFail();

        $this->assertSame('successful', $tx->status);
        $this->assertEqualsWithDelta(18.0, (float) $tx->seller_earning, 0.001);
        $this->assertEqualsWithDelta(12.0, (float) $tx->platform_commission, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $tx->rider_fee, 0.001);
        $this->assertEqualsWithDelta(90.0, (float) $tx->logistics_fee, 0.001);
        $this->assertEqualsWithDelta(30.0, (float) $tx->origin_hub_fee, 0.001);
        $this->assertEqualsWithDelta(60.0, (float) $tx->pickup_rider_fee, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $tx->inter_hub_driver_fee, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $tx->destination_hub_fee, 0.001);
        $this->assertEqualsWithDelta(50.0, (float) $tx->last_mile_rider_fee, 0.001);

        $rows = EscrowRemittance::where('order_id', $order->id)->get();
        $this->assertCount(7, $rows);
        $lastMile = $rows->firstWhere('recipient_role', EscrowRemittance::ROLE_LAST_MILE_RIDER);
        $this->assertSame('cod_retained', $lastMile->settlement_mode);
        $this->assertSame('remitted', $lastMile->status);

        Payments::settleForDelivery($order->fresh());

        $this->assertSame(7, EscrowRemittance::where('order_id', $order->id)->count());
        $this->assertSame(1, PaymentTransaction::where('order_id', $order->id)->count());
    }

    public function test_cancellation_voids_escrow_rows_without_creating_earnings(): void
    {
        [$order, $sellerId] = $this->order(2, 60.0);
        $order->update(['status' => 'pending', 'payment_status' => 'pending']);
        $company = $this->company('Cancellation');
        $hub = $this->center($company, 'Hub');
        $delivery = $this->delivery($order->fresh(), $sellerId, [
            'center_id' => $hub->id,
            'destination_center_id' => $hub->id,
            'status' => 'waiting_for_rider',
            'received_at' => null,
            'delivered_at' => null,
        ]);

        Payments::recordCheckout($order->fresh());
        EscrowEarnings::syncRemittancesForOrder($order->fresh());
        $this->assertSame(7, EscrowRemittance::where('order_id', $order->id)->count());

        $order->update(['status' => 'cancelled']);
        Payments::voidForCancel($order->fresh());

        $this->assertSame(7, EscrowRemittance::where('order_id', $order->id)->count());
        $this->assertSame(7, EscrowRemittance::where('order_id', $order->id)->where('status', 'voided')->count());
        $this->assertSame(0, RiderEarning::where('delivery_id', $delivery->id)->count());
        $this->assertSame(0, HubTransferEarning::where('delivery_id', $delivery->id)->count());
    }

    public function test_active_hub_transfer_blocks_last_mile_assignment_until_custody_closes(): void
    {
        [$order, $sellerId] = $this->order(1, 60.0);
        $order->update(['status' => 'out_for_delivery']);
        $company = $this->company('Custody');
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $delivery = $this->delivery($order->fresh(), $sellerId, [
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => 'waiting_for_rider',
            'received_at' => null,
            'delivered_at' => null,
        ]);

        $transfer = HubTransfer::create([
            'reference' => $this->token('HT-ACTIVE'),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => 'in_transit',
        ]);
        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'pending',
        ]);

        $this->assertTrue(HubTransferService::hasActiveLeg($delivery->id));
        $this->assertTrue(FulfillmentWorkflow::hasActiveHubTransfer($delivery->fresh()));

        try {
            FulfillmentWorkflow::assertReady($delivery->fresh());
            $this->fail('A parcel in active hub custody must not be assigned for last-mile delivery.');
        } catch (HttpException $e) {
            $this->assertSame(409, $e->getStatusCode());
        }

        $transfer->update(['status' => 'received']);
        $this->assertFalse(HubTransferService::hasActiveLeg($delivery->id));
        $this->assertFalse(FulfillmentWorkflow::hasActiveHubTransfer($delivery->fresh()));
    }

    public function test_prepaid_settlement_keeps_last_mile_in_escrow_until_paid(): void
    {
        [$order, $sellerId] = $this->order(2, 60.0);
        $company = $this->company('Prepaid');
        $hub = $this->center($company, 'Hub');
        $rider = $this->rider($hub->id);
        $delivery = $this->delivery($order, $sellerId, [
            'center_id' => $hub->id,
            'destination_center_id' => $hub->id,
            'rider_id' => $rider->id,
            'payment_method' => 'prepaid',
            'delivery_fee' => null,
        ]);

        EscrowEarnings::syncRemittancesForOrder($order->fresh());

        $lastMile = EscrowRemittance::where('order_id', $order->id)
            ->where('recipient_role', EscrowRemittance::ROLE_LAST_MILE_RIDER)
            ->firstOrFail();
        $this->assertSame('escrow_bank_remittance', $lastMile->settlement_mode);
        $this->assertSame('pending_remittance', $lastMile->status);
        $this->assertEqualsWithDelta(50.0, (float) $lastMile->amount, 0.001);
    }

    public function test_remittance_amount_matches_cod_escrow_share(): void
    {
        [$order, $sellerId] = $this->order(2, 60.0);
        $company = $this->company('Remittance');
        $hub = $this->center($company, 'Hub');
        $rider = $this->rider($hub->id);
        $this->delivery($order, $sellerId, [
            'center_id' => $hub->id,
            'destination_center_id' => $hub->id,
            'rider_id' => $rider->id,
            'delivery_fee' => null,
        ]);

        $calc = EscrowEarnings::calculateForSeller($order->fresh(), $sellerId);
        $this->assertEqualsWithDelta(
            $calc['cod_escrow_remittance'],
            Remittances::amountForGroup($order->fresh(), $sellerId),
            0.001
        );
        // COD rider retains the last-mile fee, never more than the cash held.
        $this->assertEqualsWithDelta(50.0, $calc['cod_rider_retains'], 0.001);
        $this->assertEqualsWithDelta(120.0, $calc['cod_escrow_remittance'], 0.001);
    }

    public function test_company_and_center_scoping_deny_cross_company_and_cross_center_access(): void
    {
        $companyA = $this->company('Scope A');
        $companyB = $this->company('Scope B');
        $hubA = $this->center($companyA, 'Hub A');
        $hubA2 = $this->center($companyA, 'Hub A2');
        $hubB = $this->center($companyB, 'Hub B');

        $staffA = User::create([
            'name' => 'Scope Staff A', 'email' => $this->token('staff-a').'@test.local',
            'password' => Hash::make('secret123'), 'role' => 'logistics_staff', 'status' => 'active',
            'account_status' => 'active', 'center_id' => $hubA->id, 'logistics_company_id' => $companyA->id,
        ]);
        $staffB = User::create([
            'name' => 'Scope Staff B', 'email' => $this->token('staff-b').'@test.local',
            'password' => Hash::make('secret123'), 'role' => 'logistics_staff', 'status' => 'active',
            'account_status' => 'active', 'center_id' => $hubB->id, 'logistics_company_id' => $companyB->id,
        ]);

        [$order, $sellerId] = $this->order(1, 60.0);
        $delivery = $this->delivery($order, $sellerId, ['center_id' => $hubA->id, 'destination_center_id' => $hubA->id]);

        $this->assertTrue(LogisticsAccess::canAccessDelivery($staffA->fresh(), $delivery));
        $this->assertFalse(LogisticsAccess::canAccessDelivery($staffA->fresh(), $delivery->fresh()->fill(['center_id' => $hubA2->id])));
        $this->assertFalse(LogisticsAccess::canAccessDelivery($staffB->fresh(), $delivery));
    }

    public function test_hub_courier_cannot_read_another_drivers_transfer(): void
    {
        $company = $this->company('Courier Isolation');
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => strtoupper($this->token('PLT')),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 10,
            'capacity_kg' => 500,
            'status' => 'assigned',
        ]);
        $assigned = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck->id,
            'name' => 'Assigned Driver',
            'email' => $this->token('assigned').'@test.local',
            'password' => Hash::make('secret123'),
            'status' => 'assigned',
        ]);
        $outsider = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Outsider Driver',
            'email' => $this->token('outsider').'@test.local',
            'password' => Hash::make('secret123'),
            'status' => 'available',
        ]);
        $transfer = HubTransfer::create([
            'reference' => $this->token('HT-PRIVATE'),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $assigned->id,
            'status' => 'assigned',
        ]);

        Sanctum::actingAs($outsider, ['hub-courier']);
        $this->getJson('/api/logistics/courier/transfers/'.$transfer->id)->assertNotFound();

        Sanctum::actingAs($assigned, ['hub-courier']);
        $this->getJson('/api/logistics/courier/transfers/'.$transfer->id)->assertOk();
    }
}
