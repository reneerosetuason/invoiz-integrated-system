<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\User;
use App\Support\CustomerTracking;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A parcel may only show hub processing once the pickup rider has recorded
 * both the seller pickup and the hub handoff. Premature internal statuses must
 * never advance the customer-facing timeline.
 */
class ParcelStatusSynchronizationTest extends TestCase
{
    use DatabaseTransactions;

    private function company(): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => 'Sync Co '.bin2hex(random_bytes(3)),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company, string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name.' '.bin2hex(random_bytes(3)),
            'address' => '1 Sync Road',
            'city' => 'Test City',
            'province' => 'Metro Manila',
            'is_active' => true,
            'logistics_company_id' => $company->id,
        ]);
    }

    private function staff(LogisticsCompany $company, LogisticsCenter $center): User
    {
        return User::create([
            'name' => 'Sync Staff',
            'email' => 'sync-staff-'.bin2hex(random_bytes(6)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'is_admin' => false,
            'logistics_company_id' => $company->id,
            'center_id' => $center->id,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function rider(LogisticsCenter $center): Rider
    {
        $user = User::create([
            'name' => 'Sync Pickup Rider',
            'email' => 'sync-rider-'.bin2hex(random_bytes(6)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
        ]);

        return Rider::create([
            'user_id' => $user->id,
            'center_id' => $center->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_online' => true,
            'approved_at' => now(),
            'is_verified' => true,
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear(),
        ]);
    }

    private function buyer(): User
    {
        return User::create([
            'name' => 'Sync Buyer',
            'email' => 'sync-buyer-'.bin2hex(random_bytes(6)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'status' => 'active',
        ]);
    }

    private function order(User $buyer, string $status = 'ready_for_delivery'): Order
    {
        return Order::create([
            'buyer_id' => $buyer->id,
            'seller_id' => 1,
            'status' => $status,
            'total_amount' => 500,
        ]);
    }

    private function delivery(LogisticsCenter $origin, LogisticsCenter $destination, Order $order, array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'tracking_number' => 'TRK-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
            'sender_name' => 'Sync Seller',
            'sender_address' => 'Seller Street',
            'recipient_name' => 'Sync Buyer',
            'recipient_address' => 'Buyer Street',
        ], $extra));
    }

    public function test_receipt_is_rejected_before_pickup_and_handoff(): void
    {
        $company = $this->company();
        $origin = $this->center($company, 'Origin Hub');
        $destination = $this->center($company, 'Destination Hub');
        $staff = $this->staff($company, $origin);
        $order = $this->order($this->buyer());
        $delivery = $this->delivery($origin, $destination, $order);
        PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $origin->id,
            'status' => 'approved',
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->actingAs($staff)
            ->post(route('deliveries.receive', $delivery), ['center_id' => $origin->id])
            ->assertRedirect()
            ->assertSessionHas('error');

        $delivery->refresh();
        $this->assertSame('pending_arrival', $delivery->parcel_status);
        $this->assertNull($delivery->received_at);
        $this->assertSame('approved', $delivery->pickupRequest->status);
        $this->assertDatabaseMissing('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'received',
        ]);
    }

    public function test_receipt_succeeds_after_pickup_and_handoff_and_closes_the_pickup_request(): void
    {
        $company = $this->company();
        $origin = $this->center($company, 'Origin Hub');
        $destination = $this->center($company, 'Destination Hub');
        $staff = $this->staff($company, $origin);
        $rider = $this->rider($origin);
        $order = $this->order($this->buyer());
        $delivery = $this->delivery($origin, $destination, $order, [
            'status' => 'picked_up',
            'picked_up_at' => now(),
            'sorting_center_handoff_at' => now(),
            'sorting_center_handoff_rider_id' => $rider->id,
        ]);
        PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $origin->id,
            'status' => 'approved',
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->actingAs($staff)
            ->post(route('deliveries.receive', $delivery), ['center_id' => $origin->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $delivery->refresh();
        $this->assertSame('received', $delivery->parcel_status);
        $this->assertNotNull($delivery->received_at);
        $this->assertSame('completed', $delivery->pickupRequest->fresh()->status);
    }

    public function test_customer_label_and_timeline_ignore_unverified_hub_progress(): void
    {
        $company = $this->company();
        $origin = $this->center($company, 'Origin Hub');
        $destination = $this->center($company, 'Destination Hub');
        $buyer = $this->buyer();
        $order = $this->order($buyer);
        $delivery = $this->delivery($origin, $destination, $order, [
            'parcel_status' => 'sorted',
            'received_at' => now(),
            'scanned_at' => now(),
            'sorted_at' => now(),
        ]);
        PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $origin->id,
            'status' => 'approved',
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);
        foreach (['pickup_scheduled', 'received', 'scanned', 'sorted'] as $status) {
            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id,
                'status' => $status,
                'notes' => 'Log '.$status,
            ]);
        }

        $this->assertSame('Pickup Approved — Awaiting Rider', CustomerTracking::label($order, $delivery));

        $titles = collect(CustomerTracking::timeline($order, $delivery))->pluck('title');
        $this->assertTrue($titles->contains('Pickup Scheduled'));
        $this->assertFalse($titles->contains('Received at Logistics Hub'));
        $this->assertFalse($titles->contains('Sorted at Logistics Hub'));

        // The buyer page must not claim hub processing for this parcel.
        $this->withSession(['buyer' => ['id' => $buyer->id]])
            ->get('/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Pickup Approved — Awaiting Rider')
            ->assertDontSee('AT HUB')
            ->assertDontSee('Sorted at Logistics Hub');
    }

    public function test_customer_label_and_timeline_show_hub_progress_once_custody_is_recorded(): void
    {
        $company = $this->company();
        $origin = $this->center($company, 'Origin Hub');
        $destination = $this->center($company, 'Destination Hub');
        $rider = $this->rider($origin);
        $buyer = $this->buyer();
        $order = $this->order($buyer);
        $delivery = $this->delivery($origin, $destination, $order, [
            'parcel_status' => 'sorted',
            'picked_up_at' => now(),
            'sorting_center_handoff_at' => now(),
            'sorting_center_handoff_rider_id' => $rider->id,
            'received_at' => now(),
            'scanned_at' => now(),
            'sorted_at' => now(),
        ]);
        foreach (['received', 'scanned', 'sorted'] as $status) {
            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id,
                'status' => $status,
                'notes' => 'Log '.$status,
            ]);
        }

        $this->assertSame('Sorted at Logistics Hub', CustomerTracking::label($order, $delivery));
        $titles = collect(CustomerTracking::timeline($order, $delivery))->pluck('title');
        $this->assertTrue($titles->contains('Received at Logistics Hub'));
        $this->assertTrue($titles->contains('Sorted at Logistics Hub'));

        $this->withSession(['buyer' => ['id' => $buyer->id]])
            ->get('/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Sorted at Logistics Hub')
            ->assertSee('AT HUB');
    }
}
