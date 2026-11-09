<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupRequest;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Verifies that Buyer, Seller, and Hub Staff views conceal premature hub statuses
 * and unverified hub progress when parcels lack recorded pickup/handoff custody.
 */
class PrematureHubStatusVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    private function identity(string $role, array $extra = []): User
    {
        return User::create(array_merge([
            'name' => 'User ' . $role . ' ' . bin2hex(random_bytes(3)),
            'email' => $role . '-' . bin2hex(random_bytes(4)) . '@test.local',
            'password' => Hash::make('secret123'),
            'role' => $role,
            'is_admin' => $role === 'admin',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ], $extra));
    }

    private function center(string $name = 'Testing Hub'): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name . ' ' . bin2hex(random_bytes(3)),
            'address' => '1 Hub Avenue',
            'city' => 'Calamba',
            'province' => 'Laguna',
            'is_active' => true,
        ]);
    }

    /** @return array{0: User, 1: Seller, 2: Product} */
    private function shop(): array
    {
        $user = $this->identity('seller');
        $seller = Seller::create([
            'user_id' => $user->id,
            'store_name' => 'Verified Shop ' . bin2hex(random_bytes(3)),
            'status' => 'active',
            'approval_status' => 'approved',
            'vacation_mode' => false,
            'address_line' => '1 Shop Way',
            'barangay' => 'Shop Barangay',
            'city' => 'Calamba',
            'province' => 'Laguna',
        ]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'name' => 'Test Item ' . bin2hex(random_bytes(2)),
            'slug' => 'test-item-' . bin2hex(random_bytes(4)),
            'status' => 'active',
            'price' => 150,
        ]);

        return [$user, $seller, $product];
    }

    private function order(User $buyer, Seller $seller, Product $product): Order
    {
        $order = Order::create([
            'seller_id' => $seller->user_id,
            'buyer_id' => $buyer->id,
            'order_number' => 'ORD-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'status' => 'ready_for_delivery',
            'total_amount' => 150,
            'payment_status' => 'paid',
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'seller_id' => $seller->user_id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 1,
            'unit_price' => 150,
            'total_price' => 150,
        ]);

        return $order;
    }

    private function delivery(LogisticsCenter $center, Order $order, Seller $seller, array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'order_id' => $order->id,
            'seller_id' => $seller->user_id,
            'tracking_number' => 'TRK-' . now()->format('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3))),
            'center_id' => $center->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
            'sender_name' => 'Shop Sender',
            'sender_address' => '1 Shop Way',
            'recipient_name' => 'Buyer Recipient',
            'recipient_address' => '2 Buyer Road',
            'picked_up_at' => null,
            'sorting_center_handoff_at' => null,
            'sorting_center_handoff_rider_id' => null,
        ], $extra));
    }

    public function test_buyer_view_conceals_hub_processing_when_parcel_is_pending_pickup(): void
    {
        $center = $this->center();
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

        $response = $this->withSession(['buyer' => ['id' => $buyer->id]])
            ->get('/orders/' . $order->id);

        $response->assertOk();
        $response->assertSee('Pickup Approved — Awaiting Rider');
        $response->assertSee('Pickup Status');
        $response->assertSee('has not been collected by a rider yet');
        $response->assertDontSee('AT HUB');
        $response->assertDontSee('Sorted at Logistics Hub');
        $response->assertDontSee('Received at Logistics Hub');
    }

    public function test_seller_view_shows_awaiting_pickup_and_hides_premature_hub_stages(): void
    {
        $center = $this->center();
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

        $response = $this->actingAs($sellerUser, 'web')
            ->withSession(['active_role' => 'seller', 'seller_id' => $seller->id])
            ->get(route('seller.orders.show', $order->id));

        $response->assertOk();
        $response->assertSee($delivery->tracking_number);
        $response->assertSee('Pickup Approved — Awaiting Rider');
        $response->assertSee('Ready for Pickup');
        $response->assertSee('The parcel has not been collected yet.');
        $response->assertDontSee('Sorted at Logistics Hub');
        $response->assertDontSee('Received at Logistics Hub');
    }

    public function test_sorting_center_flags_pending_arrival_parcel_with_awaiting_pickup(): void
    {
        $center = $this->center();
        $staff = $this->identity('staff', ['center_id' => $center->id]);
        $buyer = $this->identity('buyer');
        [$sellerUser, $seller, $product] = $this->shop();
        $order = $this->order($buyer, $seller, $product);
        $delivery = $this->delivery($center, $order, $seller, [
            'parcel_status' => 'pending_arrival',
        ]);

        $response = $this->actingAs($staff, 'web')
            ->get(route('sorting-center.index', ['tab' => 'all']));

        $response->assertOk();
        $response->assertSee($delivery->tracking_number);
        $response->assertSee('pending arrival');
        $response->assertSee('Awaiting pickup');
    }

    public function test_sorting_center_flags_unverified_hub_progress_and_suppresses_processing_actions(): void
    {
        $center = $this->center();
        $staff = $this->identity('staff', ['center_id' => $center->id]);
        $buyer = $this->identity('buyer');
        [$sellerUser, $seller, $product] = $this->shop();
        $order = $this->order($buyer, $seller, $product);

        // Parcel advanced to 'sorted' without pickup or hub handoff timestamps
        $delivery = $this->delivery($center, $order, $seller, [
            'parcel_status' => 'sorted',
            'received_at' => now()->subMinutes(10),
            'scanned_at' => now()->subMinutes(8),
            'sorted_at' => now()->subMinutes(5),
            'picked_up_at' => null,
            'sorting_center_handoff_at' => null,
            'sorting_center_handoff_rider_id' => null,
        ]);

        $response = $this->actingAs($staff, 'web')
            ->get(route('sorting-center.index', ['tab' => 'all']));

        $response->assertOk();
        $response->assertSee($delivery->tracking_number);
        // Flagged with unverified warning badge because handoff custody is missing
        $response->assertSee('Receipt unverified');

        // Processing forms/actions are suppressed for unverified rows
        $content = $response->getContent();
        $pos = strpos($content, $delivery->tracking_number);
        $this->assertNotFalse($pos, 'Delivery tracking number must be found in sorting center HTML');

        $rowStart = strrpos(substr($content, 0, $pos), '<tr');
        $rowEnd = strpos($content, '</tr>', $pos);
        $rowHtml = substr($content, $rowStart, $rowEnd - $rowStart);

        $this->assertStringContainsString('Receipt unverified', $rowHtml);
        $this->assertStringNotContainsString('action="', $rowHtml, 'Form action buttons must not appear on unverified custody rows');
    }
}
