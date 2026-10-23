<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrackingAccessTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role, ?int $centerId = null): User
    {
        $this->seq++;

        return User::create([
            'name' => ucfirst($role).' '.$this->seq,
            'email' => "f10-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => $centerId,
            'email_verified_at' => now(),
        ]);
    }

    private function center(string $name): LogisticsCenter
    {
        $this->seq++;

        return LogisticsCenter::create([
            'name' => $name.' '.$this->seq,
            'address' => '1 Tracking Street',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'is_active' => true,
        ]);
    }

    private function delivery(array $overrides = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'tracking_number' => 'TRK-F10-'.str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'status' => 'out_for_delivery',
            'parcel_status' => 'scanned',
            'scanned_at' => now(),
            'sender_name' => 'Tracking Sender',
            'recipient_name' => 'Tracking Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'weight' => 1,
        ], $overrides));
    }

    private function sellerAccount(): array
    {
        $user = $this->user('seller');
        $seller = Seller::create([
            'user_id' => $user->id,
            'store_name' => 'Tracking Store '.$this->seq,
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);

        return [$user, $seller];
    }

    private function orderScenario(): array
    {
        [$sellerUser, $seller] = $this->sellerAccount();
        $buyer = $this->user('buyer');

        $order = Order::create([
            'order_number' => 'F10-ORDER-'.$this->seq,
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'status' => 'out_for_delivery',
            'payment_status' => 'pending',
            'total' => 120,
            'total_amount' => 120,
            'sub_total' => 120,
            'shipping_fee' => 50,
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => null,
            'name' => 'Tracking Product '.$this->seq,
            'slug' => 'tracking-product-'.$this->seq,
            'status' => 'active',
            'price' => 60,
            'stock' => 10,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $sellerUser->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 60,
            'total_price' => 120,
        ]);

        $delivery = $this->delivery([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
        ]);

        return [$sellerUser, $seller, $buyer, $order, $delivery];
    }

    public function test_center_staff_can_search_only_their_center_with_normalized_tracking_number(): void
    {
        $center = $this->center('Own Center');
        $staff = $this->user('staff', $center->id);
        $delivery = $this->delivery([
            'center_id' => $center->id,
            'delivery_otp' => '123456',
            'delivery_otp_expires_at' => now()->addMinutes(10),
        ]);

        $this->actingAs($staff)
            ->get(route('tracking.search', [
                'tracking_number' => '  '.strtolower($delivery->tracking_number).'  ',
            ]))
            ->assertOk()
            ->assertSee($delivery->tracking_number)
            ->assertSee('Parcel Processing')
            ->assertSee('Scanned')
            ->assertDontSee('123456');
    }

    public function test_center_staff_cannot_search_another_centers_delivery(): void
    {
        $ownCenter = $this->center('Own Center');
        $otherCenter = $this->center('Other Center');
        $staff = $this->user('staff', $ownCenter->id);
        $delivery = $this->delivery([
            'center_id' => $otherCenter->id,
            'recipient_name' => 'Cross Center Recipient',
        ]);

        $this->actingAs($staff)
            ->get(route('tracking.search', ['tracking_number' => $delivery->tracking_number]))
            ->assertOk()
            ->assertSee('No delivery found')
            ->assertDontSee($delivery->tracking_number)
            ->assertDontSee('Cross Center Recipient');
    }

    public function test_centerless_staff_and_unrouted_deliveries_do_not_bypass_center_scope(): void
    {
        $staff = $this->user('staff');
        $delivery = $this->delivery(['center_id' => null]);

        $this->actingAs($staff)
            ->get(route('tracking.search', ['tracking_number' => $delivery->tracking_number]))
            ->assertOk()
            ->assertSee('No delivery found')
            ->assertDontSee($delivery->tracking_number);
    }

    public function test_logistics_manager_can_search_unrouted_delivery_globally(): void
    {
        $manager = $this->user('logistics_admin');
        $delivery = $this->delivery(['center_id' => null]);

        $this->actingAs($manager)
            ->get(route('tracking.search', ['tracking_number' => $delivery->tracking_number]))
            ->assertOk()
            ->assertSee($delivery->tracking_number);
    }

    public function test_tracking_search_rejects_empty_or_overlong_input(): void
    {
        $manager = $this->user('logistics_admin');

        $this->actingAs($manager)
            ->get(route('tracking.search', ['tracking_number' => '   ']))
            ->assertRedirect()
            ->assertSessionHasErrors('tracking_number');

        $this->actingAs($manager)
            ->get(route('tracking.search', ['tracking_number' => str_repeat('A', 41)]))
            ->assertRedirect()
            ->assertSessionHasErrors('tracking_number');
    }

    public function test_buyer_order_page_shows_all_tracking_numbers_and_remains_buyer_scoped(): void
    {
        [, $seller, $buyer, $order, $delivery] = $this->orderScenario();
        $otherDelivery = $this->delivery([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'tracking_number' => 'TRK-F10-SECOND',
        ]);

        $this->actingAs($buyer)
            ->get('/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Shipment Tracking')
            ->assertSee('Package 1 of 2')
            ->assertSee('Package 2 of 2')
            ->assertSee($delivery->tracking_number)
            ->assertSee($otherDelivery->tracking_number);

        Sanctum::actingAs($buyer);
        $this->getJson('/api/orders')
            ->assertOk()
            ->assertJsonPath('orders.0.delivery.tracking_number', $delivery->tracking_number);

        $this->actingAs($this->user('buyer'))
            ->get('/orders/'.$order->id)
            ->assertNotFound();
    }

    public function test_buyer_order_page_shows_parcel_milestones_without_false_delivery_state(): void
    {
        [, , $buyer, $order, $delivery] = $this->orderScenario();

        $delivery->update(['parcel_status' => 'scanned', 'scanned_at' => now()]);

        $this->actingAs($buyer)
            ->get('/orders/'.$order->id)
            ->assertOk()
            ->assertSee('Parcel Processing')
            ->assertSee('AT HUB')
            ->assertSee('SCANNED')
            ->assertSee('OUT FOR DELIVERY')
            ->assertSee('This is separate from your order status.');
    }

    public function test_seller_order_page_shows_tracking_number_and_remains_seller_scoped(): void
    {
        [$sellerUser, , , $order, $delivery] = $this->orderScenario();

        $this->actingAs($sellerUser)
            ->withSession(['active_role' => 'seller'])
            ->get(route('seller.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Tracking number')
            ->assertSee($delivery->tracking_number);

        [$otherSellerUser] = $this->sellerAccount();

        $this->actingAs($otherSellerUser)
            ->withSession(['active_role' => 'seller'])
            ->get(route('seller.orders.show', $order->id))
            ->assertNotFound();
    }

    public function test_rider_lookup_only_returns_deliveries_assigned_to_the_rider(): void
    {
        $riderUser = $this->user('rider');
        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'name' => 'Tracking Rider',
            'email' => $riderUser->email,
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);

        $assigned = $this->delivery(['rider_id' => $rider->id]);
        $unassigned = $this->delivery([
            'rider_id' => null,
            'recipient_name' => 'Private Recipient',
        ]);

        Sanctum::actingAs($riderUser);

        $this->getJson('/api/logistics/rider/deliveries/lookup?tracking_number='.$assigned->tracking_number)
            ->assertOk()
            ->assertJsonPath('delivery.tracking_number', $assigned->tracking_number);

        $this->getJson('/api/logistics/rider/deliveries/lookup?tracking_number='.$unassigned->tracking_number)
            ->assertForbidden()
            ->assertJsonMissingPath('delivery');
    }

    public function test_tracking_result_marks_archived_and_exception_records_without_claiming_completion(): void
    {
        $center = $this->center('Exception Center');
        $staff = $this->user('staff', $center->id);
        $delivery = $this->delivery([
            'center_id' => $center->id,
            'status' => 'lost',
            'parcel_status' => 'dispatched',
            'failed_at' => now(),
            'failure_reason' => 'Suspected theft',
            'archived_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('tracking.search', ['tracking_number' => $delivery->tracking_number]))
            ->assertOk()
            ->assertSee('Archived')
            ->assertSee('Final Status')
            ->assertSee('Suspected theft')
            ->assertSee('Final Status: Lost');
    }
}
