<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\User;
use App\Support\CustomerTracking;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Seller and buyer timelines share CustomerTracking::timeline(). Both must
 * report first-mile progress exactly when the rider records it, and must never
 * advance to later milestones before the matching event exists.
 */
class CustomerFirstMileTimelineTest extends TestCase
{
    use DatabaseTransactions;

    private function identity(string $role): User
    {
        return User::create([
            'name' => 'First Mile '.$role,
            'email' => 'first-mile-'.bin2hex(random_bytes(6)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    /** @return array{0: User, 1: Seller, 2: Product} */
    private function shop(): array
    {
        $user = $this->identity('seller');
        $seller = Seller::create([
            'user_id' => $user->id,
            'store_name' => 'First Mile Shop '.bin2hex(random_bytes(3)),
            'status' => 'active',
            'approval_status' => 'approved',
            'vacation_mode' => false,
            'address_line' => '1 Seller Street',
            'barangay' => 'Seller Barangay',
            'city' => 'Calamba',
            'province' => 'Laguna',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'First Mile Item',
            'slug' => 'first-mile-item-'.bin2hex(random_bytes(4)),
            'status' => 'active',
            'price' => 100,
        ]);

        return [$user, $seller, $product];
    }

    private function center(string $name = 'Calamba Hub'): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name.' '.bin2hex(random_bytes(3)),
            'address' => '1 Hub Road',
            'city' => 'Calamba',
            'province' => 'Laguna',
            'is_active' => true,
        ]);
    }

    private function rider(LogisticsCenter $center): Rider
    {
        $user = $this->identity('rider');

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

    private function order(User $buyer, Seller $seller, Product $product): Order
    {
        $order = Order::create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'status' => 'ready_for_delivery',
            'total_amount' => 500,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'seller_id' => $seller->user_id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 100,
            'total_price' => 100,
        ]);

        return $order;
    }

    private function delivery(LogisticsCenter $center, Order $order, Seller $seller): Delivery
    {
        return Delivery::create([
            'order_id' => $order->id,
            'seller_id' => $seller->user_id,
            'tracking_number' => 'TRK-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
            'center_id' => $center->id,
            'destination_center_id' => $center->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
            'sender_name' => 'First Mile Seller',
            'sender_address' => '1 Seller Street',
            'recipient_name' => 'First Mile Buyer',
            'recipient_address' => '2 Buyer Street',
        ]);
    }

    private function log(Delivery $delivery, string $status, ?string $note = null): void
    {
        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => $status,
            'notes' => $note ?? 'Log '.$status,
        ]);
        $this->travel(1)->minutes();
    }

    /** @return array<int, array{title: string, note: ?string, at: mixed, category: string}> */
    private function timelineOf(Order $order, Delivery $delivery): array
    {
        return CustomerTracking::timeline($order->fresh(), $delivery->fresh());
    }

    /** @param array<int, array{title: string}> $timeline */
    private function titles(array $timeline): array
    {
        return array_map(fn (array $entry) => $entry['title'], $timeline);
    }

    public function test_first_mile_milestones_appear_only_after_the_rider_records_them(): void
    {
        $center = $this->center();
        $rider = $this->rider($center);
        $buyer = $this->identity('buyer');
        [$sellerUser, $seller, $product] = $this->shop();
        $order = $this->order($buyer, $seller, $product);
        $delivery = $this->delivery($center, $order, $seller);

        PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $center->id,
            'status' => 'approved',
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->log(
            $delivery,
            'pickup_scheduled',
            'Seller scheduled Calamba pickup for 2026-11-02T06:23. Logistics review and rider assignment remain required.'
        );

        // Requirements are still pending: the scheduling note stays truthful
        // and no rider milestone is shown yet.
        $timeline = $this->timelineOf($order, $delivery);
        $this->assertSame(['Pickup Scheduled'], $this->titles($timeline));
        $this->assertStringContainsString('remain required', (string) $timeline[0]['note']);
        $this->assertSame('Pickup Approved — Awaiting Rider', CustomerTracking::label($order->fresh(), $delivery->fresh()));

        // Logistics assigns the pickup rider.
        $delivery->update(['rider_id' => $rider->id, 'status' => 'assigned', 'assigned_at' => now()]);
        $this->log($delivery, 'assigned', 'Assigned to rider '.$rider->name.'.');

        $timeline = $this->timelineOf($order, $delivery);
        $this->assertContains('Rider Assigned for Pickup', $this->titles($timeline));
        $this->assertStringNotContainsString('remain required', implode(' ', array_filter(array_column($timeline, 'note'))));
        $this->assertSame('Rider Assigned for Pickup', CustomerTracking::label($order->fresh(), $delivery->fresh()));

        // Rider accepts the pickup request.
        $delivery->update(['status' => 'accepted', 'accepted_at' => now()]);
        $this->log($delivery, 'accepted', 'Delivery accepted by rider.');

        $timeline = $this->timelineOf($order, $delivery);
        $this->assertContains('Rider Accepted Pickup', $this->titles($timeline));
        $this->assertSame('Rider Accepted Pickup', CustomerTracking::label($order->fresh(), $delivery->fresh()));
        $this->assertNotContains('Parcel Picked Up by Rider', $this->titles($timeline));
        $this->assertNotContains('Received at Logistics Hub', $this->titles($timeline));

        // Rider starts travelling to the seller.
        $delivery->update(['status' => 'going_to_pickup']);
        $this->log($delivery, 'going_to_pickup', 'Rider is on the way to the shop.');

        $timeline = $this->timelineOf($order, $delivery);
        $this->assertContains('Rider En Route to Seller', $this->titles($timeline));
        $this->assertSame('Rider En Route to Seller', CustomerTracking::label($order->fresh(), $delivery->fresh()));
        $this->assertNotContains('Parcel Picked Up by Rider', $this->titles($timeline));
        $this->assertNotContains('Received at Logistics Hub', $this->titles($timeline));

        // Rider reaches the seller.
        $delivery->update(['status' => 'arrived_at_shop']);
        $this->log($delivery, 'arrived_at_shop', 'Rider arrived at the shop.');

        $timeline = $this->timelineOf($order, $delivery);
        $this->assertContains('Rider Arrived at Seller', $this->titles($timeline));
        $this->assertSame('Rider Arrived at Seller', CustomerTracking::label($order->fresh(), $delivery->fresh()));
        $this->assertNotContains('Parcel Picked Up by Rider', $this->titles($timeline));

        // Parcel is collected from the seller.
        $delivery->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        $this->log($delivery, 'picked_up', 'Package picked up from shop.');

        $timeline = $this->timelineOf($order, $delivery);
        $this->assertContains('Parcel Picked Up by Rider', $this->titles($timeline));
        $this->assertSame('Picked Up — En Route to Hub', CustomerTracking::label($order->fresh(), $delivery->fresh()));
        $this->assertNotContains('Received at Logistics Hub', $this->titles($timeline));

        // Hub receipt only appears after pickup and handoff evidence exists.
        $delivery->update([
            'sorting_center_handoff_at' => now(),
            'sorting_center_handoff_rider_id' => $rider->id,
        ]);
        $this->log($delivery, 'sorting_center_handoff', 'Parcel handed over to sorting center by '.$rider->name.'.');
        $delivery->update(['parcel_status' => 'received', 'received_at' => now()]);
        $this->log($delivery, 'received', 'Center receipt confirmed; pickup custody closed.');

        $timeline = $this->timelineOf($order, $delivery);
        $this->assertSame([
            'Pickup Scheduled',
            'Rider Assigned for Pickup',
            'Rider Accepted Pickup',
            'Rider En Route to Seller',
            'Rider Arrived at Seller',
            'Parcel Picked Up by Rider',
            'Handed to Logistics Hub',
            'Received at Logistics Hub',
        ], $this->titles($timeline));

        // The buyer reads the same records without the internal rider steps.
        $buyerTimeline = CustomerTracking::timeline(
            $order->fresh(),
            $delivery->fresh(),
            CustomerTracking::AUDIENCE_BUYER
        );
        $expectedBuyer = array_values(array_filter(
            $timeline,
            fn (array $entry) => ! in_array($entry['category'], ['rider_accepted', 'going_to_pickup', 'arrived_at_shop'], true)
        ));
        $this->assertEquals($expectedBuyer, $buyerTimeline);
        $this->assertSame([
            'Pickup Scheduled',
            'Rider Assigned for Pickup',
            'Parcel Picked Up by Rider',
            'Handed to Logistics Hub',
            'Received at Logistics Hub',
        ], $this->titles($buyerTimeline));
    }

    public function test_buyer_page_hides_internal_rider_steps_while_seller_page_keeps_them(): void
    {
        $center = $this->center();
        $rider = $this->rider($center);
        $buyer = $this->identity('buyer');
        [$sellerUser, $seller, $product] = $this->shop();
        $order = $this->order($buyer, $seller, $product);
        $delivery = $this->delivery($center, $order, $seller);

        PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $center->id,
            'status' => 'approved',
            'requested_at' => now(),
            'reviewed_at' => now(),
        ]);

        $this->log(
            $delivery,
            'pickup_scheduled',
            'Seller scheduled Calamba pickup for 2026-11-02T06:23. Logistics review and rider assignment remain required.'
        );
        $delivery->update(['rider_id' => $rider->id, 'status' => 'assigned', 'assigned_at' => now()]);
        $this->log($delivery, 'assigned', 'Assigned to rider '.$rider->name.'.');
        $delivery->update(['status' => 'accepted', 'accepted_at' => now()]);
        $this->log($delivery, 'accepted', 'Delivery accepted by rider.');
        $delivery->update(['status' => 'going_to_pickup']);
        $this->log($delivery, 'going_to_pickup', 'Rider is on the way to the shop.');
        $delivery->update(['status' => 'arrived_at_shop']);
        $this->log($delivery, 'arrived_at_shop', 'Rider arrived at the shop.');

        $this->assertSame('Rider Arrived at Seller', CustomerTracking::label($order->fresh(), $delivery->fresh()));
        $this->assertSame(
            'Rider Assigned for Pickup',
            CustomerTracking::label($order->fresh(), $delivery->fresh(), CustomerTracking::AUDIENCE_BUYER)
        );

        $this->withSession(['buyer' => ['id' => $buyer->id]])
            ->get('/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Rider Assigned for Pickup')
            ->assertDontSee('Rider Accepted Pickup')
            ->assertDontSee('Rider En Route to Seller')
            ->assertDontSee('Rider Arrived at Seller')
            ->assertDontSee('Parcel Picked Up by Rider')
            ->assertDontSee('Received at Logistics Hub')
            ->assertDontSee('Logistics review and rider assignment remain required');

        $this->actingAs($sellerUser, 'web')
            ->withSession(['active_role' => 'seller', 'seller_id' => $seller->id])
            ->get(route('seller.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Rider Assigned for Pickup')
            ->assertSee('Rider Accepted Pickup')
            ->assertSee('Rider En Route to Seller')
            ->assertSee('Rider Arrived at Seller')
            ->assertDontSee('Parcel Picked Up by Rider')
            ->assertDontSee('Received at Logistics Hub')
            ->assertDontSee('Logistics review and rider assignment remain required');
    }
}
