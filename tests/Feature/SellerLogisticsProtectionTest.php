<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\CommissionRate;
use App\Models\Delivery;
use App\Models\EscrowRemittance;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Seller;
use App\Models\User;
use App\Services\HubRoadDistanceService;
use App\Services\InterHubPaymentPolicy;
use App\Services\LogisticsFundingService;
use App\Services\OrderShipmentService;
use App\Services\ReturnFeeService;
use App\Services\ReturnSettlementService;
use App\Services\SellerProtectionService;
use App\Services\TripPaymentService;
use App\Support\EscrowEarnings;
use App\Support\Payments;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SellerLogisticsProtectionTest extends TestCase
{
    use DatabaseTransactions;

    private function fixture(bool $fund = false): array
    {
        EscrowEarnings::updateRates(['seller_logistics_cap_percent' => 5, 'seller_min_profit_percent' => 5,
            'origin_hub_fee' => 30, 'pickup_rider_per_item' => 30, 'inter_hub_driver_fee' => 20, 'destination_hub_fee' => 30,
            'last_mile_rider_fee' => 50, 'trip_base_payment' => 100, 'trip_rate_per_km' => 5, 'trip_bonus_50' => 50, 'trip_bonus_80' => 100, 'trip_volumetric_divisor' => 5000]);
        CommissionRate::updateOrCreate(['category_id' => null], ['rate' => 10]);
        $seller = User::factory()->create(['role' => 'seller', 'status' => 'active', 'account_status' => 'active']);
        $buyer = User::factory()->create(['role' => 'buyer', 'status' => 'active', 'account_status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin', 'is_admin' => true, 'status' => 'active', 'account_status' => 'active']);
        if ($fund) {
            LogisticsFundingService::credit($admin, 1000000, 'PROFIT-'.uniqid(), 'Isolated test platform funds.');
        }
        $address = Address::create(['buyer_id' => $buyer->id, 'recipient_name' => 'Buyer', 'phone' => '09171234567', 'address_line' => 'Test street', 'barangay' => 'Test', 'city' => 'Manila', 'province' => 'Metro Manila', 'is_default' => true]);
        $product = Product::create(['category_id' => Category::factory()->create(['name' => 'Fee category '.uniqid()])->id, 'seller_id' => $seller->id, 'name' => 'Protected shirt',
            'slug' => 'protected-'.uniqid(), 'status' => 'active', 'price' => 430, 'cost_price' => 200, 'packaging_cost' => 0, 'stock' => 10, 'weight' => '0.30kg', 'dimensions' => '6 * 6 * 16cm']);
        $order = Order::create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'address_id' => $address->id, 'status' => 'pending', 'payment_status' => 'pending', 'total_amount' => 480, 'sub_total' => 430, 'shipping_fee' => 50]);
        $item = OrderItem::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'price' => 430, 'unit_price' => 430]);
        Payment::create(['order_id' => $order->id, 'method' => 'cash_on_delivery', 'amount' => 480, 'status' => 'pending']);
        $company = LogisticsCompany::create(['name' => 'Protected logistics '.uniqid(), 'status' => 'active']);
        $from = LogisticsCenter::create(['name' => 'Origin', 'address' => 'Test Road', 'city' => 'Manila', 'province' => 'Metro Manila', 'logistics_company_id' => $company->id, 'is_active' => true]);
        $to = LogisticsCenter::create(['name' => 'Destination', 'address' => 'Other Road', 'city' => 'Lumban', 'province' => 'Laguna', 'logistics_company_id' => $company->id, 'is_active' => true]);
        $delivery = Delivery::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'tracking_number' => 'PROTECTED-'.uniqid(), 'center_id' => $from->id, 'origin_center_id' => $from->id, 'destination_center_id' => $to->id,
            'recipient_name' => 'Buyer', 'recipient_address' => 'Other Road', 'status' => 'waiting_for_rider', 'parcel_status' => 'sorted', 'weight' => 0.3, 'length_cm' => 6, 'width_cm' => 6, 'height_cm' => 16]);
        $truck = LogisticsTruck::create(['logistics_company_id' => $company->id, 'plate_number' => 'P-'.uniqid(), 'vehicle_type' => 'truck', 'status' => 'available', 'verification_status' => 'approved', 'ownership_type' => 'company',
            'capacity_kg' => 1500, 'parcel_capacity' => 100, 'cargo_length_cm' => 300, 'cargo_width_cm' => 160, 'cargo_height_cm' => 160]);
        $driver = HubTransportDriver::create(['name' => 'Protected driver', 'email' => uniqid().'@driver.test', 'password' => 'password123', 'logistics_company_id' => $company->id, 'logistics_truck_id' => $truck->id, 'status' => 'available', 'is_online' => true]);
        app()->instance(HubRoadDistanceService::class, new class extends HubRoadDistanceService
        {
            public function between(LogisticsCenter $origin, LogisticsCenter $destination): array
            {
                return ['distance' => 40.0, 'source' => 'Isolated road routing fixture'];
            }
        });

        return compact('seller', 'buyer', 'admin', 'product', 'order', 'item', 'delivery', 'from', 'to', 'truck', 'driver');
    }

    private function outbound(array $f): HubTransfer
    {
        $trip = HubTransfer::create(['reference' => 'PROTECTED-TRIP-'.uniqid(), 'payment_version' => 1, 'logistics_company_id' => $f['from']->logistics_company_id,
            'origin_center_id' => $f['from']->id, 'destination_center_id' => $f['to']->id, 'truck_id' => $f['truck']->id, 'driver_id' => $f['driver']->id,
            'status' => 'loading', 'route_distance_km' => 91.972, 'route_distance_source' => 'Recorded test road route', 'route_recorded_by' => $f['admin']->id]);
        HubTransferParcel::create(['hub_transfer_id' => $trip->id, 'delivery_id' => $f['delivery']->id, 'loading_status' => 'loaded', 'receiving_status' => 'pending']);
        TripPaymentService::confirm($trip, $f['admin'], hash('sha256', json_encode(TripPaymentService::preview($trip))));

        return $trip->fresh();
    }

    private function reverse(array $f, string $payer = 'seller'): array
    {
        $merchandise = (float) $f['item']->lineTotal;
        $merchandiseCents = (int) round($merchandise * 100);
        $case = ReturnRequest::create(['order_id' => $f['order']->id, 'order_item_id' => $f['item']->id, 'buyer_id' => $f['buyer']->id, 'seller_id' => $f['seller']->id,
            'product_id' => $f['product']->id, 'quantity' => 1, 'reason' => 'wrong_item', 'status' => 'requested', 'refund_amount' => $merchandise]);
        $s = ReturnSettlementService::ensure($case);
        $s->fill(['workflow_policy' => 'seller_fault_direct', 'resolution' => 'return_refund', 'fault' => 'seller', 'payer' => $payer,
            'maximum_refund_cents' => $merchandiseCents, 'merchandise_cents' => $merchandiseCents, 'net_refund_cents' => $merchandiseCents + 5000, 'shipping_cents' => 5000,
            'collection_center_id' => $f['to']->id, 'seller_center_id' => $f['from']->id, 'buyer_accepted_at' => now(), 'seller_accepted_at' => now(),
            'destination_verified_at' => now(), 'refund_destination' => ['account_name' => 'Buyer', 'account_number' => '09171234567']])->save();
        ReturnFeeService::apply($case, $s);
        $s->save();

        return [$case, $s];
    }

    public function test_actual_driver_pay_uses_distance_and_weight_without_subsidy_or_hidden_difference(): void
    {
        $f = $this->fixture();
        Payments::recordCheckout($f['order']);
        $trip = $this->outbound($f);
        $this->assertSame(3, $trip->payment_snapshot['pricing_version']);
        $this->assertSame(3950, $trip->payment_snapshot['total']);
        $this->assertSame(0, $trip->payment_snapshot['platform_adjustment_cents']);
        $this->assertSame($trip->payment_snapshot['allocations'], $trip->payment_snapshot['seller_budgets']);
        $this->assertEquals(39.5, EscrowRemittance::where('hub_transfer_id', $trip->id)->sole()->amount);
        $this->assertNull($f['order']->fresh()->logistics_policy_snapshot);
        $this->assertSame(0, DB::table('logistics_funding_entries')->where('order_id', $f['order']->id)->count());
        $trip->update(['status' => 'received']);
        $calc = EscrowEarnings::calculateForSeller($f['order']->fresh(), $f['seller']->id);
        $this->assertEquals(39.5, $calc['inter_hub_driver_fee']);
        $this->assertEquals(129.5, $calc['merch_logistics_deducted']);
        $this->assertEquals(257.5, $calc['seller_earning']);
        $this->assertEquals(0, $calc['platform_logistics_subsidy']);
        $this->assertTrue($calc['conserved']);
        $f['order']->update(['status' => 'delivered', 'payment_status' => 'paid']);
        Payments::settleForDelivery($f['order']->fresh());
        $tx = PaymentTransaction::where('order_id', $f['order']->id)->sole();
        $this->assertEquals(0, $tx->platform_logistics_subsidy);
        $this->assertEquals(43, $tx->platform_commission);
    }

    public function test_missing_product_cost_and_packaging_do_not_block_checkout_or_fulfillment(): void
    {
        $f = $this->fixture();
        $f['product']->update(['cost_price' => null, 'packaging_cost' => null]);
        Payments::recordCheckout($f['order']);
        $f['order']->update(['status' => 'processing']);
        app(OrderShipmentService::class)->ensure($f['order']->fresh());
        $trip = $this->outbound($f);
        $this->assertSame(3950, $trip->payment_snapshot['total']);
        $this->assertNull($f['order']->fresh()->logistics_policy_snapshot);
    }

    public function test_unfunded_old_snapshot_does_not_reactivate_subsidy_on_acceptance(): void
    {
        $f = $this->fixture();
        $f['order']->update(['logistics_policy_snapshot' => ['sellers' => [$f['seller']->id => ['baseline_subsidy_cents' => 10000]]], 'status' => 'processing']);
        $this->assertNull(SellerProtectionService::policy($f['order'], $f['seller']->id));
        app(OrderShipmentService::class)->ensure($f['order']);
        $this->assertSame(0, DB::table('logistics_funding_entries')->where('order_id', $f['order']->id)->count());
    }

    public function test_product_cost_does_not_change_driver_charge_or_block_checkout(): void
    {
        $f = $this->fixture();
        $f['product']->update(['cost_price' => 270]);
        // Production cost is intentionally excluded from the agreed fee formula.
        Payments::recordCheckout($f['order']);
        $trip = $this->outbound($f);
        $this->assertSame(3950, $trip->payment_snapshot['total']);
    }

    public function test_return_uses_distance_and_actual_weight_without_subsidy(): void
    {
        $f = $this->fixture();
        $trip = $this->outbound($f);
        $trip->update(['status' => 'received']);
        $f['delivery']->update(['status' => 'delivered']);
        [$case, $s] = $this->reverse($f);
        $quote = $s->fee_snapshot['return_trip'];
        $this->assertEquals(40, $quote['distance']);
        $this->assertEquals(0.3, $quote['weight']);
        $this->assertNull($quote['volume']);
        $this->assertSame(3, $quote['pricing_version']);
        $this->assertSame(2848, $quote['total']);
        $this->assertSame(16848, (int) $s->transport_cents);
        $this->assertSame(0, (int) $s->platform_subsidy_cents);
        $this->assertSame(0, (int) $s->seller_reimbursement_cents);
        $this->assertSame(48000, (int) $s->net_refund_cents);
        $this->assertSame(16848, $s->payerBudgetCents());
        $this->assertFalse($s->allocations()->where('role', 'seller_protection_reimbursement')->exists());
    }

    public function test_returned_item_fee_uses_its_weight_instead_of_the_multi_product_order_value(): void
    {
        $f = $this->fixture();
        $extra = $f['product']->replicate();
        $extra->slug = 'extra-'.uniqid();
        $extra->price = 10000;
        $extra->save();
        OrderItem::create(['order_id' => $f['order']->id, 'seller_id' => $f['seller']->id, 'product_id' => $extra->id, 'product_name' => 'Other product', 'quantity' => 1, 'unit_price' => 10000]);
        $f['order']->update(['total_amount' => 10480, 'sub_total' => 10430]);
        $trip = $this->outbound($f);
        $trip->update(['status' => 'received']);
        $f['delivery']->update(['status' => 'delivered']);
        [$case, $s] = $this->reverse($f);
        $this->assertSame(2848, $s->fee_snapshot['return_trip']['total']);
        $this->assertGreaterThan(0, $s->fee_snapshot['return_trip']['total']);
        $this->assertSame(0, (int) $s->platform_subsidy_cents);
    }

    public function test_weighted_allocation_redistributes_without_exceeding_any_parcel_cap(): void
    {
        $quote = ['total' => 1001, 'rates' => ['trip_volumetric_divisor' => 5000], 'parcels' => [
            ['id' => 1, 'weight' => 100, 'length_cm' => 1, 'width_cm' => 1, 'height_cm' => 1],
            ['id' => 2, 'weight' => 1, 'length_cm' => 1, 'width_cm' => 1, 'height_cm' => 1],
            ['id' => 3, 'weight' => 1, 'length_cm' => 1, 'width_cm' => 1, 'height_cm' => 1],
        ]];
        $result = InterHubPaymentPolicy::apply($quote, [1 => 1, 2 => 1000, 3 => 1000]);
        $this->assertSame([1 => 1, 2 => 500, 3 => 500], $result['allocations']);
        $this->assertSame(1001, array_sum($result['allocations']));
    }

    public function test_admin_and_seller_pages_have_no_subsidy_or_packaging_forms(): void
    {
        $f = $this->fixture();
        $adminPage = $this->actingAs($f['admin'])->get(route('admin.commission'));
        $adminPage->assertOk()->assertDontSee('funds_confirmed')->assertDontSee('Fund logistics')->assertSee('Handling Per Parcel');
        file_put_contents(base_path('.tools/interhub-admin-preview.html'), $adminPage->getContent());
        Seller::factory()->create(['user_id' => $f['seller']->id, 'status' => 'active', 'approval_status' => 'approved', 'line_of_business' => 'Retail', 'seller_type' => 'individual', 'tin' => 'TEST', 'region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Manila', 'barangay' => 'Test', 'postal_code' => '1000', 'address_line' => 'Test Road']);
        $sellerPage = $this->actingAs($f['seller'])->get(route('seller.orders.show', $f['order']));
        $sellerPage->assertOk()->assertDontSee('Platform-funded logistics')->assertDontSee('profit protected');
        file_put_contents(base_path('.tools/interhub-seller-preview.html'), $sellerPage->getContent());
        $this->assertFalse(Route::has('admin.commission.logistics-funds'));
    }

    public static function checkoutMethods(): array
    {
        return [['cash_on_delivery'], ['gcash']];
    }

    #[DataProvider('checkoutMethods')]
    public function test_buyer_can_preview_and_place_an_order_with_zero_platform_subsidy_funds(string $method): void
    {
        $f = $this->fixture(false);
        $f['product']->update(['cost_price' => null, 'packaging_cost' => null]);
        $store = Seller::factory()->create(['user_id' => $f['seller']->id, 'status' => 'active', 'approval_status' => 'approved',
            'line_of_business' => 'Retail', 'seller_type' => 'individual', 'tin' => 'TEST', 'region' => 'NCR', 'province' => 'Metro Manila',
            'city' => 'Manila', 'barangay' => 'Test', 'postal_code' => '1000', 'address_line' => 'Test Road']);
        $cart = Cart::create(['buyer_id' => $f['buyer']->id]);
        $line = CartItem::create(['cart_id' => $cart->id, 'product_id' => $f['product']->id, 'quantity' => 1]);
        Sanctum::actingAs($f['buyer']);
        $selection = ['cart_item_ids' => [$line->id]];
        $this->postJson('/api/orders/preview', $selection)->assertOk()->assertJsonPath('quote.total', 480);
        $payload = $selection + ['address_id' => $f['order']->address_id, 'payment_method' => $method];
        if ($method === 'gcash') {
            Storage::fake('public');
            Storage::fake('private');
            Storage::disk('public')->put('test-gcash-qr.png', 'Isolated QR fixture');
            $store->update(['gcash_qr_path' => 'test-gcash-qr.png', 'gcash_account_name' => 'Test store']);
            Storage::disk('private')->put('receipt-fixture.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jQwAAAABJRU5ErkJggg=='));
            $payload['gcash_proof'] = new UploadedFile(Storage::disk('private')->path('receipt-fixture.png'), 'receipt.png', 'image/png', null, true);
        }
        $response = $this->postJson('/api/orders/checkout', $payload)->assertCreated();
        $placed = Order::findOrFail($response->json('order.id'));
        $this->assertNull($placed->logistics_policy_snapshot);
        $this->assertSame('pending', $placed->status);
        $this->assertEquals(60, EscrowEarnings::calculateForSeller($placed, $f['seller']->id)['merch_logistics_deducted']);
        $this->assertSame(0, LogisticsFundingService::balance()['reserved']);
    }

    private function oneKilogramFixture(string $method = 'cash_on_delivery'): array
    {
        $f = $this->fixture();
        EscrowEarnings::updateRates(['inter_hub_handling_fee' => 20, 'inter_hub_rate_per_km' => 0.20, 'inter_hub_rate_per_kg_km' => 0.04]);
        $f['product']->update(['price' => 599, 'cost_price' => 350, 'weight' => '1kg', 'dimensions' => '30 * 25 * 2cm']);
        $f['item']->update(['price' => 599, 'unit_price' => 599, 'total_price' => 599]);
        $f['order']->update(['sub_total' => 599, 'total_amount' => 649]);
        $f['delivery']->update(['weight' => 1, 'length_cm' => 30, 'width_cm' => 25, 'height_cm' => 2]);
        $f['order']->payment()->update(['method' => $method, 'amount' => 649, 'status' => $method === 'gcash' ? 'paid' : 'pending']);
        if ($method === 'gcash') {
            $f['order']->update(['payment_status' => 'paid']);
            \App\Models\GcashEscrowDeposit::create(['order_id' => $f['order']->id, 'seller_id' => $f['seller']->id, 'amount' => 649, 'status' => 'funded', 'confirmed_at' => now()]);
        }
        return $f;
    }

    #[DataProvider('checkoutMethods')]
    public function test_agreed_fee_settles_to_the_same_order_earnings_for_cod_and_gcash(string $method): void
    {
        $f = $this->oneKilogramFixture($method);
        Payments::recordCheckout($f['order']->fresh());
        $trip = $this->outbound($f);
        $this->assertSame(4207, $trip->payment_snapshot['total']);
        $this->assertSame(4207, $trip->payment_snapshot['allocations'][$f['delivery']->id]);
        $trip->update(['status' => 'received']);
        $trip->parcels()->update(['receiving_status' => 'received', 'condition' => 'good']);
        $f['delivery']->update(['status' => 'delivered']);
        $f['order']->update(['status' => 'delivered']);
        Payments::settleForDelivery($f['order']->fresh());
        $tx = PaymentTransaction::where('order_id', $f['order']->id)->sole();
        $this->assertEquals(42.07, $tx->inter_hub_driver_fee);
        $this->assertEquals(407.03, $tx->seller_earning);
        $this->assertEquals(59.90, $tx->platform_commission);
        $this->assertEquals(0, $tx->platform_logistics_subsidy);
        $calc = EscrowEarnings::calculateForSeller($f['order']->fresh(), $f['seller']->id);
        $this->assertTrue($calc['conserved']);
        $this->assertEquals(42.07, EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole()->amount);
        TripPaymentService::refreshEligibility($trip->fresh());
        $this->assertSame('pending_remittance', EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole()->status);
        $this->assertSame(0.0, (float) EscrowRemittance::where('order_id', $f['order']->id)->where('recipient_role', 'inter_hub_driver')->sole()->amount);
        $adminPage = $this->actingAs($f['admin'])->get(route('admin.payments'))->assertOk()->assertDontSee('Included in truck trip')->assertSee('42.07');
        file_put_contents(base_path('.tools/distance-weight-admin-payments.html'), $adminPage->getContent());
        $tripPage = $this->get(route('hub-transfers.show', $trip))->assertOk()->assertSee('Weight and distance fees')->assertSee('42.07');
        file_put_contents(base_path('.tools/distance-weight-trip-preview.html'), $tripPage->getContent());
    }

    #[DataProvider('checkoutMethods')]
    public function test_completed_trip_can_be_released_and_accepted_during_an_open_return(string $method): void
    {
        foreach (['requested', 'approved', 'item_received'] as $status) {
            $f = $this->oneKilogramFixture($method);
            Payments::recordCheckout($f['order']->fresh());
            $trip = $this->outbound($f);
            $trip->update(['status' => 'received']);
            $trip->parcels()->update(['receiving_status' => 'received', 'condition' => 'good']);
            $f['delivery']->update(['status' => 'delivered']);
            $f['order']->update(['status' => 'delivered']);
            Payments::settleForDelivery($f['order']->fresh());
            $case = ReturnRequest::create(['order_id' => $f['order']->id, 'order_item_id' => $f['item']->id,
                'buyer_id' => $f['buyer']->id, 'seller_id' => $f['seller']->id, 'product_id' => $f['product']->id,
                'quantity' => 1, 'reason' => 'wrong_item', 'status' => $status, 'refund_amount' => 599]);
            \App\Models\PayoutAccount::create(['owner_type' => 'driver', 'owner_id' => $f['driver']->id,
                'details' => ['provider' => 'Test bank', 'holder' => $f['driver']->name, 'account' => '000123456789']]);
            TripPaymentService::refreshEligibility($trip->fresh());
            $payout = EscrowRemittance::where('settlement_reference', 'TRIP-'.$trip->id)->sole();
            $this->assertSame('pending_remittance', $payout->status);
            $this->assertNull(TripPaymentService::releaseHoldReason($trip->fresh()));
            $this->actingAs($f['admin'])->post(route('admin.escrow.remit', $payout), ['bank_reference' => 'RETURN-OPEN-'.$case->id])
                ->assertRedirect()->assertSessionHasNoErrors();
            $this->assertSame('remitted', $payout->fresh()->status);
            $this->assertTrue($payout->fresh()->accept($f['driver']));
            $this->assertEquals(42.07, $payout->fresh()->amount);
            $this->assertEquals(42.07, TripPaymentService::orderBudget($f['order']->fresh())['released']);
            $this->assertSame($status, $case->fresh()->status);
            $this->assertEquals(599, $case->fresh()->refund_amount);
        }
    }

    #[DataProvider('checkoutMethods')]
    public function test_all_completed_workers_can_be_paid_during_returns_and_keep_their_payment_after_refund(string $method): void
    {
        foreach (['requested', 'approved', 'item_received'] as $returnStatus) {
            $f = $this->oneKilogramFixture($method);
            Payments::recordCheckout($f['order']->fresh());
            $f['order']->update(['status' => 'delivered']);
            $f['delivery']->update(['status' => 'delivered', 'delivered_at' => now()]);
            Payments::settleForDelivery($f['order']->fresh());
            $case = ReturnRequest::create(['order_id' => $f['order']->id, 'order_item_id' => $f['item']->id,
                'buyer_id' => $f['buyer']->id, 'seller_id' => $f['seller']->id, 'product_id' => $f['product']->id,
                'quantity' => 1, 'reason' => 'wrong_item', 'status' => $returnStatus, 'refund_amount' => 599]);
            $workers = $this->workerPayouts($f);
            foreach ($workers as [$payout, $recipient]) {
                $reference = 'COMPLETED-WORK-'.$payout->id;
                $this->actingAs($f['admin'])->post(route('admin.escrow.remit', $payout), ['bank_reference' => $reference])
                    ->assertRedirect()->assertSessionHasNoErrors();
                $this->assertSame('remitted', $payout->fresh()->status);
            }
            $case->update(['status' => 'refunded']);
            $f['order']->update(['payment_status' => 'refunded']);
            $f['order']->payment()->update(['status' => 'refunded']);
            PaymentTransaction::where('order_id', $f['order']->id)->update(['status' => 'refunded', 'refunded_amount' => 649]);
            EscrowEarnings::syncRemittancesForOrder($f['order']->fresh());
            foreach ($workers as [$payout, $recipient]) {
                $this->assertSame('remitted', $payout->fresh()->status);
                $this->assertTrue($payout->fresh()->accept($recipient));
                $this->assertSame('accepted', $payout->fresh()->status);
                $this->assertSame('COMPLETED-WORK-'.$payout->id, $payout->fresh()->bank_reference);
                $this->assertEquals(20, $payout->fresh()->amount);
            }
            EscrowEarnings::syncRemittancesForOrder($f['order']->fresh());
            foreach ($workers as [$payout]) $this->assertSame('accepted', $payout->fresh()->status);
        }
    }

    private function workerPayouts(array $f): array
    {
        $workers = [];
        foreach (EscrowRemittance::WORKER_ROLES as $role) {
            $center = $role === 'origin_hub' ? $f['from'] : $f['to'];
            $hub = in_array($role, ['origin_hub', 'destination_hub'], true);
            $recipient = $role === 'inter_hub_driver' ? $f['driver'] : User::factory()->create([
                'role' => $hub ? 'logistics_staff' : 'rider', 'status' => 'active', 'account_status' => 'active',
                'center_id' => $center->id, 'logistics_company_id' => $center->logistics_company_id]);
            $rider = !$hub && $role !== 'inter_hub_driver' ? \App\Models\Rider::create([
                'user_id' => $recipient->id, 'name' => $recipient->name, 'email' => uniqid().'@worker.test',
                'phone' => '09171234567', 'center_id' => $center->id, 'status' => 'available', 'vehicle_type' => 'motorcycle']) : null;
            $payout = EscrowRemittance::updateOrCreate(['order_id' => $f['order']->id, 'seller_id' => $f['seller']->id, 'recipient_role' => $role], [
                'delivery_id' => $f['delivery']->id, 'recipient_name' => $recipient->name,
                'recipient_user_id' => $role === 'inter_hub_driver' ? null : $recipient->id,
                'hub_transport_driver_id' => $role === 'inter_hub_driver' ? $recipient->id : null,
                'rider_id' => $rider?->id, 'logistics_center_id' => $hub ? $center->id : null,
                'amount' => 20, 'status' => 'pending_remittance', 'settlement_mode' => 'escrow_bank_remittance']);
            \App\Models\PayoutAccount::updateOrCreate(['owner_type' => $hub ? 'hub' : ($role === 'inter_hub_driver' ? 'driver' : 'user'),
                'owner_id' => $hub ? $center->id : $recipient->id],
                ['details' => ['provider' => 'Test bank', 'holder' => $recipient->name, 'account' => '000123456789']]);
            $workers[] = [$payout, $recipient];
        }
        return $workers;
    }

    public function test_batch_releases_completed_cod_workers_during_return_but_keeps_seller_hold(): void
    {
        $f = $this->oneKilogramFixture();
        Payments::recordCheckout($f['order']->fresh());
        $f['order']->update(['status' => 'delivered']);
        $f['delivery']->update(['status' => 'delivered', 'delivered_at' => now()]);
        Payments::settleForDelivery($f['order']->fresh());
        $this->reverse($f);
        $workers = $this->workerPayouts($f);
        $seller = EscrowRemittance::where('order_id', $f['order']->id)->where('recipient_role', 'seller')->sole();
        $seller->update(['status' => 'pending_remittance']);
        \App\Models\PayoutAccount::create(['owner_type' => 'user', 'owner_id' => $f['seller']->id,
            'details' => ['provider' => 'Test bank', 'holder' => $f['seller']->name, 'account' => '000123456789']]);
        $this->actingAs($f['admin'])->post(route('admin.escrow.remit-all'))->assertRedirect()->assertSessionHasNoErrors();
        foreach ($workers as [$payout]) $this->assertSame('remitted', $payout->fresh()->status);
        $this->assertSame('pending_remittance', $seller->fresh()->status);
    }

    #[DataProvider('checkoutMethods')]
    public function test_seller_sees_and_confirms_admin_released_cod_and_gcash_payouts(string $method): void
    {
        $f = $this->oneKilogramFixture($method);
        Payments::recordCheckout($f['order']->fresh());
        $f['order']->update(['status' => 'delivered']);
        $f['delivery']->update(['status' => 'delivered', 'delivered_at' => now()]);
        Payments::settleForDelivery($f['order']->fresh());
        $payout = EscrowRemittance::where('order_id', $f['order']->id)->where('recipient_role', 'seller')->sole();
        $payout->update(['status' => 'pending_remittance']);
        \App\Models\PayoutAccount::create(['owner_type' => 'user', 'owner_id' => $f['seller']->id,
            'details' => ['provider' => 'Test bank', 'holder' => $f['seller']->name, 'account' => '000123456789']]);
        $reference = 'SELLER-ADMIN-RELEASE-'.$payout->id;
        $this->actingAs($f['admin'])->post(route('admin.escrow.remit', $payout), ['bank_reference' => $reference])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('remitted', $payout->fresh()->status);
        $other = User::factory()->create(['role' => 'seller', 'status' => 'active', 'account_status' => 'active']);
        foreach ([$f['seller'], $other] as $owner) {
            Seller::create(['user_id' => $owner->id, 'store_name' => 'Payout shop '.uniqid(),
                'line_of_business' => 'General Merchandise', 'approval_status' => 'approved', 'status' => 'active']);
        }
        $this->actingAs($other)->withSession(['active_role' => 'seller'])->get(route('seller.remittances.index'))
            ->assertOk()->assertDontSee($reference);
        $this->post(route('seller.gcash.accept-payout', $payout), ['confirm_received' => 1])->assertNotFound();
        $page = $this->actingAs($f['seller'])->withSession(['active_role' => 'seller'])->get(route('seller.remittances.index'))
            ->assertOk()->assertSee('Admin escrow payouts')->assertSee($reference)->assertSee('Released - confirm receipt')
            ->assertSee('Confirm payout received');
        $page->assertViewHas('escrowPayouts', fn ($rows) => $rows->contains('id', $payout->id));
        $this->get(route('seller.dashboard'))->assertOk()->assertSee('Payouts to confirm:')
            ->assertViewHas('totals', fn ($totals) => $totals['escrow_payout_pending'] === 1
                && (float) $totals['escrow_payout_amount'] === (float) $payout->amount);
        file_put_contents(base_path('.tools/seller-'.$method.'-escrow-preview.html'), $page->getContent());
        $legacyBefore = \App\Models\Remittance::where('order_id', $f['order']->id)->get()->toJson();
        $this->post(route('seller.gcash.accept-payout', $payout), ['confirm_received' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted', $payout->fresh()->status);
        $this->assertSame($reference, $payout->fresh()->bank_reference);
        $this->assertSame($legacyBefore, \App\Models\Remittance::where('order_id', $f['order']->id)->get()->toJson());
        $this->get(route('seller.remittances.index'))->assertOk()->assertSee($reference)->assertSee('Receipt confirmed');
        $this->get(route('seller.dashboard'))->assertOk()
            ->assertViewHas('totals', fn ($totals) => $totals['escrow_payout_pending'] === 0);
        $this->post(route('seller.gcash.accept-payout', $payout), ['confirm_received' => 1])->assertRedirect();
        $this->assertSame('accepted', $payout->fresh()->status);
    }

    #[DataProvider('checkoutMethods')]
    public function test_new_return_quotes_use_the_same_formula_for_cod_and_gcash_orders(string $method): void
    {
        $f = $this->oneKilogramFixture($method);
        // A newly requested return also uses the new rates when its outbound trip used an earlier policy.
        [$case, $agreement] = $this->reverse($f);
        $this->assertTrue(ReturnFeeService::usesDynamicTrip($case));
        $this->assertSame(3, $agreement->fee_snapshot['inter_hub_pricing_version']);
        $this->assertSame(2960, $agreement->fee_snapshot['return_trip']['total']); // 20 + 40*.20 + 1*40*.04.
        $this->assertSame(16960, (int) $agreement->transport_cents);
        $this->assertSame(0, (int) $agreement->platform_subsidy_cents);
        $this->assertSame(16960, $agreement->payerBudgetCents());
        $this->assertSame(64900, (int) $agreement->net_refund_cents); // Driver pay never silently reduces seller-fault refunds.
    }

    public function test_confirmed_fee_keeps_frozen_rates_when_settings_change(): void
    {
        $f = $this->oneKilogramFixture(); $trip = $this->outbound($f);
        EscrowEarnings::updateRates(['inter_hub_handling_fee' => 50, 'inter_hub_rate_per_km' => 1, 'inter_hub_rate_per_kg_km' => 1]);
        $this->assertSame(4207, TripPaymentService::preview($trip)['total']);
        TripPaymentService::assertUnchanged($trip);
        $this->assertSame(4207, $trip->fresh()->payment_snapshot['total']);
    }

    public function test_rate_changes_require_a_new_preview_before_confirmation(): void
    {
        $f = $this->oneKilogramFixture();
        $trip = HubTransfer::create(['reference' => 'RATE-'.uniqid(), 'payment_version' => 1, 'logistics_company_id' => $f['from']->logistics_company_id,
            'origin_center_id' => $f['from']->id, 'destination_center_id' => $f['to']->id, 'truck_id' => $f['truck']->id, 'driver_id' => $f['driver']->id,
            'status' => 'loading', 'route_distance_km' => 91.972, 'route_distance_source' => 'Reviewed fixture road route']);
        HubTransferParcel::create(['hub_transfer_id' => $trip->id, 'delivery_id' => $f['delivery']->id, 'loading_status' => 'loaded']);
        $hash = hash('sha256', json_encode(TripPaymentService::preview($trip)));
        EscrowEarnings::updateRates(['inter_hub_handling_fee' => 25]);
        try { TripPaymentService::confirm($trip, $f['admin'], $hash); $this->fail('Stale price was confirmed.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        $this->assertNull($trip->fresh()->payment_confirmed_at);
        $this->assertSame(0, EscrowRemittance::where('hub_transfer_id', $trip->id)->count());
    }

    public function test_existing_capped_confirmation_is_not_repriced_to_the_new_fee(): void
    {
        $f = $this->oneKilogramFixture(); $trip = $this->outbound($f);
        $snapshot = $trip->payment_snapshot;
        $snapshot['pricing_version'] = 2;
        $snapshot['truck'] = $f['truck']->only(['capacity_kg', 'parcel_capacity', 'cargo_length_cm', 'cargo_width_cm', 'cargo_height_cm']);
        $snapshot['parcels'] = [$f['delivery']->only(['id', 'weight', 'length_cm', 'width_cm', 'height_cm'])];
        $snapshot['total'] = 2995;
        $snapshot['allocations'] = [$f['delivery']->id => 2995];
        $snapshot['seller_budgets'] = $snapshot['allocations'];
        $trip->update(['payment_snapshot' => $snapshot]);
        EscrowRemittance::where('hub_transfer_id', $trip->id)->update(['amount' => 29.95]);
        $this->assertSame(2995, TripPaymentService::preview($trip->fresh())['total']);
        TripPaymentService::assertUnchanged($trip->fresh());
        $this->assertEquals(29.95, EscrowRemittance::where('hub_transfer_id', $trip->id)->sole()->amount);
    }

    public function test_new_admin_rates_validate_without_changing_other_worker_rates(): void
    {
        $f = $this->fixture();
        $data = ['origin_hub_fee' => 30, 'pickup_rider_per_item' => 30, 'destination_hub_fee' => 30, 'last_mile_rider_fee' => 50,
            'inter_hub_handling_fee' => 20, 'inter_hub_rate_per_km' => 0.20, 'inter_hub_rate_per_kg_km' => 0.04];
        $this->actingAs($f['buyer'])->post(route('admin.commission.rates.earnings'), $data)->assertForbidden();
        $this->actingAs($f['admin'])->post(route('admin.commission.rates.earnings'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(20.0, EscrowEarnings::rates()['inter_hub_handling_fee']);
        $this->assertSame(0.20, EscrowEarnings::rates()['inter_hub_rate_per_km']);
        $this->assertSame(0.04, EscrowEarnings::rates()['inter_hub_rate_per_kg_km']);
        $this->assertSame(30.0, EscrowEarnings::rates()['origin_hub_fee']);
        $this->post(route('admin.commission.rates.earnings'), array_replace($data, ['inter_hub_rate_per_kg_km' => -0.04]))->assertSessionHasErrors('inter_hub_rate_per_kg_km');
        $this->assertSame(0.04, EscrowEarnings::rates()['inter_hub_rate_per_kg_km']);
    }

    public function test_return_assignment_keeps_the_reviewed_truck_and_driver(): void
    {
        $f = $this->oneKilogramFixture(); [$case, $agreement] = $this->reverse($f);
        \App\Services\ReturnTripPricingService::assertAssignment($case, $agreement, ['driver_id' => $f['driver']->id]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        \App\Services\ReturnTripPricingService::assertAssignment($case, $agreement, ['driver_id' => $f['driver']->id + 100000]);
    }

    public function test_buyer_fault_cost_recovery_includes_only_the_orders_actual_driver_share(): void
    {
        $f = $this->oneKilogramFixture(); Payments::recordCheckout($f['order']->fresh()); $trip = $this->outbound($f);
        $trip->update(['status' => 'received']); $trip->parcels()->update(['receiving_status' => 'received', 'condition' => 'good']);
        $f['delivery']->update(['status' => 'delivered']); $f['order']->update(['status' => 'delivered']);
        Payments::settleForDelivery($f['order']->fresh());
        [$case, $agreement] = $this->reverse($f);
        $costs = \App\Services\BuyerFaultReturnService::originalCosts($case, $agreement);
        $this->assertSame(4207, $costs['shares_cents']['inter_hub_driver']);
        $this->assertSame(19197, $costs['seller_funded_costs_cents']);
    }

    public function test_full_return_reuses_original_measurements_when_product_changes_or_lacks_measurements(): void
    {
        $f = $this->oneKilogramFixture();
        $f['product']->update(['weight' => '50kg', 'dimensions' => '999 * 999 * 999cm']);
        [$case, $agreement] = $this->reverse($f);
        $this->assertSame(2960, $agreement->fee_snapshot['return_trip']['total']);
        $this->assertEquals(1, $agreement->fee_snapshot['return_trip']['weight']);
        $f['product']->update(['weight' => null, 'dimensions' => null]);
        ReturnFeeService::apply($case, $agreement);
        $this->assertSame(2960, $agreement->fee_snapshot['return_trip']['total']);
        $this->assertSame(64900, (int) $agreement->net_refund_cents);
    }
    public function test_outbound_and_return_payment_work_without_parcel_or_truck_dimensions(): void
    {
        $f = $this->oneKilogramFixture();
        $f['truck']->update(['cargo_length_cm' => null, 'cargo_width_cm' => null, 'cargo_height_cm' => null]);
        $f['delivery']->update(['length_cm' => null, 'width_cm' => null, 'height_cm' => null]);
        $f['product']->update(['dimensions' => null]);
        $trip = $this->outbound($f);
        $this->assertSame(4207, $trip->payment_snapshot['total']);
        $trip->update(['status' => 'received']);
        $f['delivery']->update(['status' => 'delivered']);
        [$case, $agreement] = $this->reverse($f);
        $this->assertSame(2960, $agreement->fee_snapshot['return_trip']['total']);
        \App\Services\ReturnTripPricingService::assertAssignment($case, $agreement, ['driver_id' => $f['driver']->id]);
        $this->assertArrayNotHasKey('cargo_length_cm', $trip->payment_snapshot['truck']);
    }

}
