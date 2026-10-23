<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\Order;
use App\Models\Product;
use App\Models\Seller;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceAreaServiceabilityTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function user(string $role, ?int $centerId = null): User
    {
        $this->seq++;

        return User::create([
            'name' => ucfirst($role) . ' ' . $this->seq,
            'email' => "f13-{$role}-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => $role,
            'is_admin' => $role === 'admin',
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
            'name' => $name . ' ' . $this->seq,
            'address' => '1 F13 Street',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'is_active' => true,
        ]);
    }

    private function area(LogisticsCenter $center, string $name = 'Coverage Area', bool $active = true): ServiceArea
    {
        return ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => $name,
            'is_active' => $active,
        ]);
    }

    private function delivery(LogisticsCenter $center, array $overrides = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'tracking_number' => 'F13-' . str_pad((string) $this->seq, 4, '0', STR_PAD_LEFT),
            'center_id' => $center->id,
            'sender_name' => 'F13 Sender',
            'recipient_name' => 'F13 Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'status' => 'assigned',
            'parcel_status' => 'scanned',
            'scanned_at' => now(),
            'weight' => 1,
        ], $overrides));
    }

    private function seller(array $overrides = []): Seller
    {
        return Seller::factory()->create(array_merge([
            'store_name' => 'F13 Store ' . ++$this->seq,
            'status' => 'approved',
            'approval_status' => 'approved',
            'address_line' => '1 Seller Street',
            'barangay' => 'Seller Barangay',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
        ], $overrides));
    }

    private function buyerAddress(User $buyer, array $overrides = []): Address
    {
        return Address::create(array_merge([
            'buyer_id' => $buyer->id,
            'recipient_name' => $buyer->name,
            'phone' => '09170000000',
            'address_line' => '1 Buyer Street',
            'barangay' => 'Buyer Barangay',
            'city' => 'Quezon City',
            'province' => 'Metro Manila',
            'is_default' => true,
        ], $overrides));
    }

    private function addCartLine(User $buyer, Seller $seller, float $price, int $quantity = 1): Product
    {
        $this->seq++;

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => null,
            'name' => 'F13 Product ' . $this->seq,
            'slug' => 'f13-product-' . $this->seq,
            'status' => 'active',
            'price' => $price,
            'stock' => 10,
        ]);

        $cart = Cart::firstOrCreate(['buyer_id' => $buyer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);

        return $product;
    }

    public function test_center_staff_cannot_access_or_change_service_areas(): void
    {
        $center = $this->center('Admin Only Center');
        $staff = $this->user('logistics_staff', $center->id);
        $area = $this->area($center);

        $this->actingAs($staff)->get('/logistics/service-areas')->assertForbidden();
        $this->actingAs($staff)->get("/logistics/service-areas/{$area->id}")->assertForbidden();
        $this->actingAs($staff)->get('/logistics/service-areas/create')->assertForbidden();

        $this->actingAs($staff)
            ->post('/logistics/service-areas', [
                'logistics_center_id' => $center->id,
                'name' => 'Unauthorized Area',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->put("/logistics/service-areas/{$area->id}", [
                'logistics_center_id' => $center->id,
                'name' => 'Changed Area',
            ])
            ->assertForbidden();

        $this->actingAs($staff)
            ->post("/logistics/service-areas/{$area->id}/toggle")
            ->assertForbidden();

        $this->assertSame('Coverage Area', $area->fresh()->name);
        $this->assertTrue($area->fresh()->is_active);
    }

    public function test_logistics_admin_service_area_changes_are_audited(): void
    {
        $center = $this->center('Audited Center');
        $admin = $this->user('admin');
        $name = 'F13 Audited Zone ' . ++$this->seq;

        $this->actingAs($admin)
            ->post('/logistics/service-areas', [
                'logistics_center_id' => $center->id,
                'name' => $name,
                'description' => 'Initial zone',
            ])
            ->assertRedirect(route('service-areas.index'));

        $area = ServiceArea::where('logistics_center_id', $center->id)->where('name', $name)->firstOrFail();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service_area.created',
            'entity' => ServiceArea::class,
            'entity_id' => $area->id,
        ]);

        $this->actingAs($admin)
            ->put("/logistics/service-areas/{$area->id}", [
                'logistics_center_id' => $center->id,
                'name' => $name . ' Updated',
                'description' => 'Updated zone',
            ])
            ->assertRedirect(route('service-areas.show', $area));

        $this->actingAs($admin)
            ->post("/logistics/service-areas/{$area->id}/toggle")
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service_area.updated',
            'entity' => ServiceArea::class,
            'entity_id' => $area->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'service_area.deactivated',
            'entity' => ServiceArea::class,
            'entity_id' => $area->id,
        ]);
        $this->assertFalse($area->fresh()->is_active);
    }

    public function test_sorting_rejects_a_service_area_from_another_center(): void
    {
        $centerA = $this->center('Source Center');
        $centerB = $this->center('Destination Center');
        $foreignArea = $this->area($centerA, 'Foreign Area');
        $delivery = $this->delivery($centerA);
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->post("/logistics/deliveries/{$delivery->id}/sort", [
                'destination_center_id' => $centerB->id,
                'service_area_id' => $foreignArea->id,
            ])
            ->assertSessionHasErrors('service_area_id');

        $this->assertSame('scanned', $delivery->fresh()->parcel_status);
        $this->assertNull($delivery->fresh()->service_area_id);

        $validArea = $this->area($centerB, 'Destination Area');

        $this->actingAs($admin)
            ->post("/logistics/deliveries/{$delivery->id}/sort", [
                'destination_center_id' => $centerB->id,
                'service_area_id' => $validArea->id,
            ])
            ->assertRedirect();

        $this->assertSame('sorted', $delivery->fresh()->parcel_status);
        $this->assertSame($validArea->id, $delivery->fresh()->service_area_id);
    }

    public function test_sorting_rejects_an_inactive_service_area(): void
    {
        $center = $this->center('Inactive Area Center');
        $inactive = $this->area($center, 'Inactive Area', false);
        $delivery = $this->delivery($center);
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->post("/logistics/deliveries/{$delivery->id}/sort", [
                'destination_center_id' => $center->id,
                'service_area_id' => $inactive->id,
            ])
            ->assertSessionHasErrors('service_area_id');

        $this->assertSame('scanned', $delivery->fresh()->parcel_status);
    }

    public function test_center_staff_cannot_sort_a_delivery_from_another_center(): void
    {
        $centerA = $this->center('Staff Center');
        $centerB = $this->center('Other Center');
        $staff = $this->user('logistics_staff', $centerA->id);
        $areaB = $this->area($centerB, 'Other Area');
        $delivery = $this->delivery($centerB);

        $this->actingAs($staff)
            ->post("/logistics/deliveries/{$delivery->id}/sort", [
                'destination_center_id' => $centerB->id,
                'service_area_id' => $areaB->id,
            ])
            ->assertForbidden();

        $this->assertSame('scanned', $delivery->fresh()->parcel_status);
    }

    public function test_checkout_keeps_flat_per_seller_fee_without_guessing_a_service_area(): void
    {
        $buyer = $this->user('buyer');
        $address = $this->buyerAddress($buyer);
        $sellerA = $this->seller();
        $sellerB = $this->seller();
        $this->addCartLine($buyer, $sellerA, 60);
        $this->addCartLine($buyer, $sellerB, 40);

        $center = $this->center('Name Match Center');
        $this->area($center, $address->city);

        $oldOrder = Order::create([
            'order_number' => 'INV-F13-HISTORICAL-' . $buyer->id,
            'seller_id' => $sellerA->id,
            'buyer_id' => $buyer->id,
            'status' => 'completed',
            'total' => 137,
            'total_amount' => 137,
            'sub_total' => 100,
            'shipping_fee' => 37,
        ]);

        Sanctum::actingAs($buyer);

        $response = $this->postJson('/api/orders/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'cash_on_delivery',
        ])->assertCreated();

        $order = Order::findOrFail($response->json('order.id'));
        $this->assertSame(100.0, (float) $order->shipping_fee);
        $this->assertSame(200.0, (float) $order->total_amount);
        $this->assertSame(37.0, (float) $oldOrder->fresh()->shipping_fee);

        $deliveries = Delivery::where('order_id', $order->id)->get();
        $this->assertCount(2, $deliveries);
        foreach ($deliveries as $delivery) {
            $this->assertSame(Order::SHIPPING_FEE_PER_SELLER, (float) $delivery->delivery_fee);
            $this->assertNull($delivery->service_area_id);
            $this->assertSame('cash_on_delivery', $delivery->payment_method);
        }

        $codBySeller = $deliveries->mapWithKeys(fn (Delivery $delivery) => [
            (int) $delivery->seller_id => (float) $delivery->amount_to_collect,
        ])->all();

        $this->assertSame(110.0, $codBySeller[(int) $sellerA->id]);
        $this->assertSame(90.0, $codBySeller[(int) $sellerB->id]);
        $this->assertSame((float) $order->total_amount, round(array_sum($codBySeller), 2));
    }

    public function test_web_checkout_uses_flat_fee_and_does_not_guess_a_service_area(): void
    {
        $buyer = $this->user('buyer');
        $address = $this->buyerAddress($buyer);
        $seller = $this->seller();
        $product = $this->addCartLine($buyer, $seller, 60);

        $center = $this->center('Web Name Match Center');
        $this->area($center, $address->city);

        $this->withSession([
            'checkout_single' => ['id' => $product->id, 'qty' => 1, 'variant_id' => null],
        ])->actingAs($buyer)
            ->post('/checkout', [
                'address_id' => $address->id,
                'payment_method' => 'cod',
            ])
            ->assertRedirect('/orders')
            ->assertSessionHas('success');

        $order = Order::where('buyer_id', $buyer->id)->firstOrFail();
        $this->assertSame((int) $seller->id, (int) $order->seller_id);
        $this->assertSame(50.0, (float) $order->shipping_fee);
        $this->assertSame(110.0, (float) $order->total_amount);

        $delivery = Delivery::where('order_id', $order->id)->firstOrFail();
        $this->assertSame(Order::SHIPPING_FEE_PER_SELLER, (float) $delivery->delivery_fee);
        $this->assertNull($delivery->service_area_id);
        $this->assertSame('cash_on_delivery', $delivery->payment_method);
        $this->assertSame(110.0, (float) $delivery->amount_to_collect);
    }

    public function test_checkout_rejects_an_incomplete_seller_pickup_address_without_creating_an_order(): void
    {
        $buyer = $this->user('buyer');
        $address = $this->buyerAddress($buyer);
        $seller = $this->seller(['city' => null]);
        $this->addCartLine($buyer, $seller, 60);

        $beforeOrders = Order::count();
        $beforeDeliveries = Delivery::count();

        Sanctum::actingAs($buyer);

        $this->postJson('/api/orders/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'cash_on_delivery',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Delivery is unavailable because the seller pickup address is incomplete (missing: city).'
            );

        $this->assertSame($beforeOrders, Order::count());
        $this->assertSame($beforeDeliveries, Delivery::count());
        $this->assertSame(1, CartItem::where('cart_id', Cart::where('buyer_id', $buyer->id)->value('id'))->count());
    }

    public function test_checkout_rejects_an_incomplete_buyer_dropoff_address(): void
    {
        $buyer = $this->user('buyer');
        $address = $this->buyerAddress($buyer, ['barangay' => null]);
        $seller = $this->seller();
        $this->addCartLine($buyer, $seller, 60);

        $beforeOrders = Order::count();

        Sanctum::actingAs($buyer);

        $this->postJson('/api/orders/checkout', [
            'address_id' => $address->id,
            'payment_method' => 'cash_on_delivery',
        ])
            ->assertStatus(422)
            ->assertJsonPath(
                'message',
                'Delivery is unavailable because the selected drop-off address is incomplete (missing: barangay).'
            );

        $this->assertSame($beforeOrders, Order::count());
    }
}
