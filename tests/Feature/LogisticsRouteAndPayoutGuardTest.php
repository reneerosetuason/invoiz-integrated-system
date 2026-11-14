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

class LogisticsRouteAndPayoutGuardTest extends TestCase
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


    private function remoteFixture(): array
    {
        $f = $this->fixture();
        $city = 'Destination '.uniqid();
        $province = 'Province '.uniqid();
        $f['to']->update(['city' => $city, 'province' => $province]);
        $f['order']->update(['status' => 'ready_for_delivery', 'shipping_address' => ['city' => $city, 'province' => $province]]);
        $f['delivery']->update(['recipient_address' => '2 Road, '.$city, 'received_at' => now(), 'parcel_status' => 'sorted']);
        $f['delivery'] = $f['delivery']->fresh();
        return $f;
    }

    public function test_missing_destination_is_inferred_from_buyer_city_and_cannot_be_assigned_at_origin(): void
    {
        $f = $this->remoteFixture();
        $this->assertEquals($f['to']->id, $f['delivery']->suggestedDestination()['center_id']);
        $this->assertSame('hub_processing', $f['delivery']->riderAssignmentStage());
        $before = $f['delivery']->getAttributes();
        try { \App\Services\FulfillmentWorkflow::assertReady($f['delivery']); $this->fail('Remote delivery was allowed.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        $this->assertSame($before, $f['delivery']->fresh()->getAttributes());
    }

    public function test_same_origin_destination_cannot_bypass_buyer_coverage(): void
    {
        $f = $this->remoteFixture();
        $f['delivery']->update(['destination_center_id' => $f['from']->id]);
        $this->assertNotNull($f['delivery']->fresh()->lastMileRouteBlockReason());
        $this->assertSame('hub_processing', $f['delivery']->fresh()->riderAssignmentStage());
    }

    public function test_origin_pickup_is_allowed_and_last_mile_waits_for_destination_sorting(): void
    {
        $f = $this->remoteFixture();
        $f['delivery']->update(['received_at' => null, 'parcel_status' => 'pending_arrival']);
        \App\Models\PickupRequest::create(['delivery_id' => $f['delivery']->id, 'center_id' => $f['from']->id, 'status' => 'approved', 'requested_at' => now()]);
        \App\Services\FulfillmentWorkflow::assertReady($f['delivery']->fresh());
        $this->assertSame('pickup', $f['delivery']->fresh()->riderAssignmentStage());
        $f['delivery']->update(['center_id' => $f['to']->id, 'destination_center_id' => $f['to']->id, 'received_at' => now(), 'parcel_status' => 'received']);
        try { \App\Services\FulfillmentWorkflow::assertReady($f['delivery']->fresh()); $this->fail('Unsorted parcel allowed.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(409, $e->getStatusCode()); }
        $f['delivery']->update(['parcel_status' => 'sorted']);
        \App\Services\FulfillmentWorkflow::assertReady($f['delivery']->fresh());
        $this->assertNull($f['delivery']->fresh()->lastMileRouteBlockReason());
    }

    public function test_review_corrects_uncollected_route_and_rejects_parcels_already_collected(): void
    {
        $f = $this->remoteFixture();
        $f['delivery']->update(['destination_center_id' => $f['from']->id, 'status' => 'assigned']);
        DB::transaction(fn () => \App\Services\FulfillmentWorkflow::reviewDestination(\App\Services\FulfillmentWorkflow::lock($f['delivery']), $f['admin']->id));
        $this->assertEquals($f['to']->id, $f['delivery']->fresh()->destination_center_id);
        $this->assertSame('waiting_for_rider', $f['delivery']->fresh()->status);
        $this->assertDatabaseHas('delivery_status_logs', ['delivery_id' => $f['delivery']->id, 'status' => 'destination_reviewed']);
        $f['delivery']->update(['sorting_center_pickup_at' => now()]);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        DB::transaction(fn () => \App\Services\FulfillmentWorkflow::reviewDestination(\App\Services\FulfillmentWorkflow::lock($f['delivery']), $f['admin']->id));
    }

    private function payout(array $f): EscrowRemittance
    {
        return EscrowRemittance::create(['order_id' => $f['order']->id, 'seller_id' => $f['seller']->id, 'recipient_role' => 'seller',
            'recipient_user_id' => $f['seller']->id, 'recipient_name' => 'Seller', 'amount' => 10, 'status' => 'pending_remittance']);
    }

    public function test_missing_bank_details_block_individual_http_release_and_hide_release_form(): void
    {
        $f = $this->fixture();
        $line = $this->payout($f);
        $this->actingAs($f['admin'])->post(route('admin.escrow.remit', $line), ['bank_reference' => 'GUARD-TEST'])
            ->assertSessionHasErrors('payout_account');
        $this->assertSame('pending_remittance', $line->fresh()->status);
        $this->assertNull($line->fresh()->bank_reference);
        $page = $this->get(route('admin.payments'))->assertOk()->assertSee('Release unavailable');
        $this->assertStringNotContainsString('action="'.route('admin.escrow.remit', $line).'"', $page->getContent());
        file_put_contents(base_path('.tools/missing-bank-release-preview.html'), $page->getContent());
    }

    public function test_batch_cannot_release_a_recipient_without_bank_details(): void
    {
        $f = $this->fixture(); $line = $this->payout($f);
        $this->actingAs($f['admin'])->post(route('admin.escrow.remit-all'))->assertRedirect();
        $this->assertSame('pending_remittance', $line->fresh()->status);
        $this->assertNull($line->fresh()->bank_reference);
    }

    public function test_saved_account_allows_release_while_batch_skips_a_missing_account(): void
    {
        $f = $this->fixture();
        $f['order']->update(['status' => 'delivered']);
        $f['delivery']->update(['status' => 'delivered']);
        $ready = $this->payout($f);
        $other = User::factory()->create(['role' => 'rider']);
        $blocked = EscrowRemittance::create(['order_id' => $f['order']->id, 'seller_id' => $f['seller']->id, 'recipient_role' => 'pickup_rider',
            'recipient_user_id' => $other->id, 'recipient_name' => 'Missing account', 'amount' => 15, 'status' => 'pending_remittance']);
        \App\Models\PayoutAccount::create(['owner_type' => 'user', 'owner_id' => $f['seller']->id,
            'details' => ['provider' => 'Test bank', 'holder' => 'Seller', 'account' => '000123456789']]);
        $this->assertTrue(\App\Support\PayoutAccounts::ready($ready));
        $this->actingAs($f['admin'])->post(route('admin.escrow.remit-all'))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('remitted', $ready->fresh()->status);
        $this->assertSame('pending_remittance', $blocked->fresh()->status);
        $ready->update(['status' => 'pending_remittance']);
        $this->post(route('admin.escrow.remit', $ready), ['bank_reference' => 'VALID-ACCOUNT-TEST'])->assertSessionHasNoErrors();
        $this->assertSame('remitted', $ready->fresh()->status);
    }

    public function test_remote_assignment_is_blocked_over_http_and_staff_can_review_the_destination(): void
    {
        $f = $this->remoteFixture();
        $staff = User::factory()->create(['role' => 'logistics_staff', 'status' => 'active', 'account_status' => 'active',
            'center_id' => $f['from']->id, 'logistics_company_id' => $f['from']->logistics_company_id]);
        $rider = \App\Models\Rider::create(['center_id' => $f['from']->id, 'name' => 'Local rider', 'email' => uniqid().'@rider.test', 'phone' => '09170000000',
            'vehicle_type' => 'motorcycle', 'vehicle_capacity_kg' => 50, 'status' => 'available', 'is_online' => true]);
        $this->actingAs($staff)->post(route('deliveries.assign-rider', $f['delivery']), ['rider_id' => $rider->id])->assertStatus(409);
        $this->assertNull($f['delivery']->fresh()->rider_id);
        // Reproduce the pre-fix uncollected assignment shown in the report.
        $f['delivery']->update(['rider_id' => $rider->id, 'status' => 'assigned', 'destination_center_id' => $f['from']->id]);
        $page = $this->get(route('deliveries.show', $f['delivery']))->assertOk()->assertSee('Review destination hub');
        $this->assertStringNotContainsString('action="'.route('deliveries.assign-rider', $f['delivery']).'"', $page->getContent());
        file_put_contents(base_path('.tools/remote-assignment-blocked-preview.html'), $page->getContent());
        $this->post(route('deliveries.review-destination', $f['delivery']))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertEquals($f['to']->id, $f['delivery']->fresh()->destination_center_id);
        $this->assertNull($f['delivery']->fresh()->rider_id);
        $this->assertSame('waiting_for_rider', $f['delivery']->fresh()->status);
        $this->actingAs($f['admin'])->post(route('deliveries.review-destination', $f['delivery']))->assertForbidden();
    }
}
