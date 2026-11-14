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
        $product = Product::create(['category_id' => Category::factory()->create()->id, 'seller_id' => $seller->id, 'name' => 'Protected shirt',
            'slug' => 'protected-'.uniqid(), 'status' => 'active', 'price' => 430, 'cost_price' => 200, 'packaging_cost' => 0, 'stock' => 10, 'weight' => '0.30kg', 'dimensions' => '6 * 6 * 16cm']);
        $order = Order::create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'address_id' => $address->id, 'status' => 'pending', 'payment_status' => 'pending', 'total_amount' => 480, 'sub_total' => 430, 'shipping_fee' => 50]);
        $item = OrderItem::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'product_id' => $product->id, 'product_name' => $product->name, 'quantity' => 1, 'price' => 430, 'unit_price' => 430]);
        Payment::create(['order_id' => $order->id, 'method' => 'cash_on_delivery', 'amount' => 480, 'status' => 'pending']);
        $company = LogisticsCompany::create(['name' => 'Protected logistics', 'status' => 'active']);
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
        $case = ReturnRequest::create(['order_id' => $f['order']->id, 'order_item_id' => $f['item']->id, 'buyer_id' => $f['buyer']->id, 'seller_id' => $f['seller']->id,
            'product_id' => $f['product']->id, 'quantity' => 1, 'reason' => 'wrong_item', 'status' => 'requested', 'refund_amount' => 430]);
        $s = ReturnSettlementService::ensure($case);
        $s->fill(['workflow_policy' => 'seller_fault_direct', 'resolution' => 'return_refund', 'fault' => 'seller', 'payer' => $payer,
            'maximum_refund_cents' => 43000, 'merchandise_cents' => 43000, 'net_refund_cents' => 48000, 'shipping_cents' => 5000,
            'collection_center_id' => $f['to']->id, 'seller_center_id' => $f['from']->id, 'buyer_accepted_at' => now(), 'seller_accepted_at' => now(),
            'destination_verified_at' => now(), 'refund_destination' => ['account_name' => 'Buyer', 'account_number' => '09171234567']])->save();
        ReturnFeeService::apply($case, $s);
        $s->save();

        return [$case, $s];
    }

    public function test_actual_driver_pay_is_capped_without_subsidy_or_hidden_difference(): void
    {
        $f = $this->fixture();
        Payments::recordCheckout($f['order']);
        $trip = $this->outbound($f);
        $this->assertSame(55986, $trip->payment_snapshot['uncapped_total']);
        $this->assertSame(2150, $trip->payment_snapshot['total']);
        $this->assertSame(0, $trip->payment_snapshot['platform_adjustment_cents']);
        $this->assertSame($trip->payment_snapshot['allocations'], $trip->payment_snapshot['seller_budgets']);
        $this->assertEquals(21.5, EscrowRemittance::where('hub_transfer_id', $trip->id)->sole()->amount);
        $this->assertNull($f['order']->fresh()->logistics_policy_snapshot);
        $this->assertSame(0, DB::table('logistics_funding_entries')->where('order_id', $f['order']->id)->count());
        $trip->update(['status' => 'received']);
        $calc = EscrowEarnings::calculateForSeller($f['order']->fresh(), $f['seller']->id);
        $this->assertEquals(21.5, $calc['inter_hub_driver_fee']);
        $this->assertEquals(111.5, $calc['merch_logistics_deducted']);
        $this->assertEquals(275.5, $calc['seller_earning']);
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
        $this->assertSame(2150, $trip->payment_snapshot['total']);
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

    public function test_recorded_margin_limits_driver_charge_without_blocking_checkout(): void
    {
        $f = $this->fixture();
        $f['product']->update(['cost_price' => 270]);
        // 430 - 270 - 43 commission - 90 other logistics - 21.50 profit = 5.50.
        Payments::recordCheckout($f['order']);
        $trip = $this->outbound($f);
        $this->assertSame(550, $trip->payment_snapshot['total']);
    }

    public function test_return_uses_distance_weight_dimensions_and_capped_actual_payment_without_subsidy(): void
    {
        $f = $this->fixture();
        $trip = $this->outbound($f);
        $trip->update(['status' => 'received']);
        $f['delivery']->update(['status' => 'delivered']);
        [$case, $s] = $this->reverse($f);
        $quote = $s->fee_snapshot['return_trip'];
        $this->assertEquals(40, $quote['distance']);
        $this->assertEquals(0.3, $quote['weight']);
        $this->assertEqualsWithDelta(0.000576, $quote['volume'], 0.000000001);
        $this->assertSame(30000, $quote['uncapped_total']);
        $this->assertSame(2150, $quote['total']);
        $this->assertSame(16150, (int) $s->transport_cents);
        $this->assertSame(0, (int) $s->platform_subsidy_cents);
        $this->assertSame(0, (int) $s->seller_reimbursement_cents);
        $this->assertSame(48000, (int) $s->net_refund_cents);
        $this->assertSame(16150, $s->payerBudgetCents());
        $this->assertFalse($s->allocations()->where('role', 'seller_protection_reimbursement')->exists());
    }

    public function test_one_returned_item_cannot_use_the_entire_multi_product_order_budget(): void
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
        $this->assertLessThanOrEqual(2150, $s->fee_snapshot['return_trip']['total']);
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
        $adminPage->assertOk()->assertDontSee('funds_confirmed')->assertDontSee('Fund logistics')->assertSee('Maximum inter-hub charge');
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
}
