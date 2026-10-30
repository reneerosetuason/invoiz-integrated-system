<?php

namespace Tests\Feature;

use App\Models\{Address, Cart, Delivery, LogisticsCenter, Order, OrderItem, PickupRequest, Product, Rider, Seller, User};
use App\Services\DeliveryOtpService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;

// Exercise the seller, center and rider boundaries through their real routes.
class OrderFulfillmentWorkflowTest extends \Tests\TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function identity(string $role): User
    {
        return User::create(['name' => 'Flow Audit '.$role, 'email' => 'flow-'.(++$this->seq).'@test.local',
            'password' => 'AuditPassword123!', 'role' => $role, 'is_admin' => false,
            'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()]);
    }

    private function shop(): array
    {
        $user = $this->identity('seller');
        $seller = Seller::create(['user_id' => $user->id, 'store_name' => 'Audit Shop '.$user->id,
            'status' => 'active', 'approval_status' => 'approved', 'vacation_mode' => false,
            'address_line' => '1 Audit Street', 'barangay' => 'Audit Barangay', 'city' => 'Audit City', 'province' => 'Audit Province']);
        $product = Product::create(['seller_id' => $user->id, 'name' => 'Audit Product '.$user->id,
            'status' => 'active', 'price' => 100, 'stock' => 10]);
        return [$user, $seller, $product];
    }

    private function buyerAddress(User $buyer): Address
    {
        return Address::create(['buyer_id' => $buyer->id, 'recipient_name' => 'Audit Buyer', 'phone' => '09170000000',
            'address_line' => '2 Audit Street', 'barangay' => 'Audit Barangay', 'city' => 'Audit City', 'province' => 'Audit Province']);
    }

    private function center(): LogisticsCenter
    {
        return LogisticsCenter::create(['name' => 'Audit Center', 'address' => '3 Audit Street', 'city' => 'Audit City', 'is_active' => true]);
    }

    private function rider(LogisticsCenter $center): Rider
    {
        $user = $this->identity('rider');
        return Rider::create(['user_id' => $user->id, 'center_id' => $center->id, 'name' => $user->name, 'email' => $user->email,
            'phone' => '09170000000', 'vehicle_type' => 'motorcycle', 'status' => 'available', 'is_online' => true,
            'approved_at' => now(), 'is_verified' => true, 'vehicle_verification' => 'verified', 'license_expiry' => now()->addYear()]);
    }

    private function order(string $status = 'pending'): array
    {
        [$seller, , $product] = $this->shop();
        $buyer = $this->identity('buyer');
        $order = Order::create(['seller_id' => $seller->id, 'buyer_id' => $buyer->id, 'status' => $status,
            'total_amount' => 150, 'sub_total' => 100, 'shipping_fee' => 50, 'payment_status' => 'pending']);
        OrderItem::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'product_id' => $product->id,
            'product_name' => $product->name, 'quantity' => 1, 'unit_price' => 100, 'total_price' => 100]);
        return [$order, $seller, $buyer];
    }

    private function shipment(Order $order, Rider $rider, string $status, array $extra = []): Delivery
    {
        return Delivery::create(array_merge(['order_id' => $order->id, 'seller_id' => $order->seller_id,
            'rider_id' => $rider->id, 'center_id' => $rider->center_id, 'status' => $status, 'parcel_status' => 'pending_arrival',
            'sender_name' => 'Audit Shop', 'sender_address' => '1 Audit Street', 'recipient_name' => 'Audit Buyer',
            'recipient_address' => '2 Audit Street', 'payment_method' => 'cash_on_delivery', 'amount_to_collect' => 150, 'delivery_fee' => 50], $extra));
    }

    public function test_checkout_populates_the_shipment_and_waits_for_seller_preparation(): void
    {
        [, , $product] = $this->shop();
        $buyer = $this->identity('buyer');
        $address = $this->buyerAddress($buyer);
        Cart::headerFor($buyer->id)->items()->create(['product_id' => $product->id, 'quantity' => 1]);
        Sanctum::actingAs($buyer);
        $id = $this->postJson('/api/orders/checkout', ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery'])
            ->assertCreated()->json('order.id');
        $delivery = Delivery::where('order_id', $id)->firstOrFail();
        $this->assertStringContainsString('1 Audit Street', $delivery->sender_address);
        $this->assertStringContainsString('2 Audit Street', $delivery->recipient_address);
        $this->assertSame('09170000000', $delivery->recipient_phone);
        $this->assertSame($product->name, $delivery->items->sole()->name);
        $this->assertNotNull($delivery->pickupRequest);
        $this->assertSame(0, PickupRequest::pendingReview($this->identity('logistics_admin'))->count());
    }

    public function test_web_checkout_uses_the_same_populated_shipment_and_pickup_queue(): void
    {
        [, , $product] = $this->shop();
        $buyer = $this->identity('buyer');
        $address = $this->buyerAddress($buyer);
        $this->actingAs($buyer, 'web')->post('/checkout', ['checkout_mode' => 'single', 'single' => $product->id,
            'qty' => 1, 'address_id' => $address->id, 'payment_method' => 'cod'])
            ->assertRedirect('/orders')->assertSessionMissing('error');
        $delivery = Delivery::whereHas('order', fn ($q) => $q->where('buyer_id', $buyer->id))->sole();
        $this->assertNotNull($delivery->pickupRequest);
        $this->assertCount(1, $delivery->items);
        $this->assertStringContainsString('Audit Street', $delivery->sender_address);
    }

    public function test_full_workflow_uses_two_riders_and_completes_the_buyers_order(): void
    {
        $center = $this->center();
        $pickup = $this->rider($center);
        $lastMile = $this->rider($center);
        [$order, $seller, $buyer] = $this->order();
        $address = $this->buyerAddress($buyer);
        $order->update(['address_id' => $address->id, 'shipping_address' => $address->toShippingSnapshot()]);
        $delivery = app(\App\Services\OrderShipmentService::class)->ensure($order);
        $tracking = $delivery->tracking_number;
        foreach (['accept', 'process', 'pack'] as $action) {
            $this->actingAs($seller, 'web')->withSession(['active_role' => 'seller'])
                ->post(route('seller.orders.status', $order), ['action' => $action])->assertRedirect()->assertSessionHas('success');
        }
        $manager = $this->identity('logistics_admin');
        $this->assertSame(1, PickupRequest::pendingReview($manager)->count());
        $this->actingAs($manager, 'web')->post(route('pickup-requests.route', $delivery->pickupRequest), ['center_id' => $center->id])->assertRedirect()->assertSessionHas('success');
        $this->post(route('pickup-requests.approve', $delivery->pickupRequest))->assertRedirect()->assertSessionHas('success');
        $this->actingAs($manager, 'web')->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $pickup->id])
            ->assertRedirect()->assertSessionHas('success');
        Sanctum::actingAs($pickup->user);
        $base = "/api/logistics/rider/deliveries/{$delivery->id}";
        $this->postJson("$base/accept")->assertOk();
        $this->patchJson("$base/status", ['status' => 'going_to_pickup'])->assertOk();
        $this->postJson("$base/pickup")->assertOk();
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $this->patchJson("$base/status", ['status' => 'out_for_delivery'])->assertStatus(409);
        $this->postJson("$base/sorting-center-handoff")->assertOk();
        $this->postJson("$base/sorting-center-handoff")->assertOk();
        $this->actingAs($manager, 'web')->post(route('deliveries.receive', $delivery), ['center_id' => $center->id])->assertRedirect()->assertSessionHas('success');
        $this->assertNull($delivery->fresh()->rider_id);
        $this->assertSame('available', $pickup->fresh()->status);
        $this->assertSame('waiting_for_rider', $delivery->fresh()->status);
        $this->actingAs($manager, 'web')->patch(route('deliveries.scan', $delivery))->assertRedirect()->assertSessionHas('success');
        $area = \App\Models\ServiceArea::create(['logistics_center_id' => $center->id, 'name' => 'Audit Area', 'is_active' => true]);
        $this->post(route('deliveries.sort', $delivery), ['destination_center_id' => $center->id, 'service_area_id' => $area->id])->assertRedirect()->assertSessionHas('success');
        $this->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $lastMile->id])->assertRedirect()->assertSessionHas('success');
        Sanctum::actingAs($lastMile->user);
        $this->postJson("$base/sorting-center-pickup")->assertStatus(409);
        $this->actingAs($manager, 'web')->post(route('deliveries.dispatch', $delivery))->assertRedirect()->assertSessionHas('success');
        Sanctum::actingAs($lastMile->user);
        $this->postJson("$base/sorting-center-pickup")->assertOk()->assertJsonPath('delivery.pickup_location.name', $center->name);
        $this->postJson("$base/sorting-center-pickup")->assertOk();
        $this->patchJson("$base/status", ['status' => 'out_for_delivery'])->assertOk();
        $this->patchJson("$base/status", ['status' => 'arrived_at_customer'])->assertOk();
        $otp = app(DeliveryOtpService::class)->ensureFor($delivery->fresh());
        $this->postJson("$base/complete", ['otp' => $otp, 'amount_received' => 150])->assertOk();
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertSame($tracking, $delivery->fresh()->tracking_number);
        $this->assertSame($pickup->id, $delivery->fresh()->sorting_center_handoff_rider_id);
        $this->assertSame($lastMile->id, $delivery->fresh()->sorting_center_pickup_rider_id);
        $this->assertDatabaseHas('rider_earnings', ['delivery_id' => $delivery->id, 'rider_id' => $lastMile->id, 'amount' => 50]);
        $this->assertDatabaseHas('remittances', ['delivery_id' => $delivery->id]);
        $this->assertDatabaseHas('delivery_proofs', ['delivery_id' => $delivery->id, 'type' => 'otp']);
        $this->assertSame(1, $order->statusHistories()->where('to_status', 'delivered')->count());
        Sanctum::actingAs($pickup->user);
        $this->getJson('/api/logistics/rider/history')->assertOk()->assertJsonPath('history.0.status', 'pickup_completed')->assertJsonPath('history.0.earned', 0);
        $this->getJson($base)->assertOk()->assertJsonPath('delivery.read_only', true);
        $this->getJson('/api/logistics/rider/history?status=delivered')->assertOk()->assertJsonCount(0, 'history');
    }

    public function test_pending_and_rejected_pickups_cannot_be_assigned_or_accepted(): void
    {
        $rider = $this->rider($this->center());
        [$order] = $this->order();
        $delivery = $this->shipment($order, $rider, 'waiting_for_rider');
        PickupRequest::create(['delivery_id' => $delivery->id, 'center_id' => $rider->center_id, 'status' => 'rejected', 'requested_at' => now()]);
        $this->actingAs($this->identity('logistics_admin'), 'web')->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $rider->id])->assertStatus(409);
        $delivery->update(['status' => 'assigned']);
        Sanctum::actingAs($rider->user);
        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/accept")->assertStatus(409);
        $order->update(['status' => 'ready_for_delivery']);
        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/accept")->assertStatus(409);
        $this->assertSame('assigned', $delivery->fresh()->status);
    }

    public function test_seller_cannot_bypass_rider_handoff_or_completion(): void
    {
        [$order, $seller] = $this->order('ready_for_delivery');
        $this->actingAs($seller, 'web')->withSession(['active_role' => 'seller'])->post(route('seller.orders.status', $order), ['action' => 'handover'])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('ready_for_delivery', $order->fresh()->status);
        $order->update(['status' => 'out_for_delivery']);
        $this->post(route('seller.orders.status', $order), ['action' => 'deliver'])->assertRedirect()->assertSessionHas('error');
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $this->get(route('seller.orders.show', $order))->assertOk()->assertDontSee('value="deliver"', false)->assertDontSee('value="handover"', false);
    }

    public function test_seller_cancellation_cancels_work_releases_rider_and_cannot_repeat_stock_restoration(): void
    {
        $rider = $this->rider($this->center());
        [$order, $seller] = $this->order('processing');
        $delivery = $this->shipment($order, $rider, 'assigned');
        $rider->update(['status' => 'delivering']);
        $product = $order->items->first()->product;
        $stock = $product->stock;
        \App\Support\OrderCancellation::bySeller($order, 'Out of stock', $seller->id);
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertSame('available', $rider->fresh()->status);
        $this->assertFalse(\App\Support\OrderCancellation::bySeller($order, 'Retry', $seller->id));
        $this->assertSame($stock + 1, $product->fresh()->stock);
        Sanctum::actingAs($rider->user);
        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/accept")->assertStatus(409);
    }

    public function test_mobile_multi_store_checkout_creates_independent_orders_and_deliveries(): void
    {
        [, , $one] = $this->shop();
        [, , $two] = $this->shop();
        $buyer = $this->identity('buyer');
        $address = $this->buyerAddress($buyer);
        $cart = Cart::headerFor($buyer->id);
        foreach ([$one, $two] as $product) { $cart->items()->create(['product_id' => $product->id, 'quantity' => 1]); }
        Sanctum::actingAs($buyer);
        $result = $this->postJson('/api/orders/checkout', ['address_id' => $address->id, 'payment_method' => 'cash_on_delivery'])
            ->assertCreated()->assertJsonCount(2, 'orders');
        $orders = Order::where('buyer_id', $buyer->id)->get();
        $this->assertCount(2, $orders);
        $this->assertEquals(300, $orders->sum('total_amount'));
        foreach ($orders as $order) {
            $this->assertCount(1, $order->items);
            $this->assertCount(1, $order->deliveries);
            $this->assertSame((int) $order->seller_id, (int) $order->delivery->seller_id);
        }
    }

    public function test_cross_center_transfer_requires_destination_receipt_before_final_assignment(): void
    {
        $origin = $this->center();
        $destination = LogisticsCenter::create(['name' => 'Destination', 'address' => '4 Audit Street', 'city' => 'Audit City', 'is_active' => true]);
        $other = LogisticsCenter::create(['name' => 'Unrelated', 'address' => '5 Audit Street', 'city' => 'Audit City', 'is_active' => true]);
        $rider = $this->rider($destination);
        [$order] = $this->order('out_for_delivery');
        $delivery = Delivery::create([
            'order_id' => $order->id, 'seller_id' => $order->seller_id, 'center_id' => $origin->id,
            'destination_center_id' => $destination->id, 'status' => 'waiting_for_rider', 'parcel_status' => 'sorted',
            'received_at' => now(), 'scanned_at' => now(), 'sorted_at' => now(), 'sorting_center_handoff_at' => now(),
        ]);
        $manager = $this->identity('logistics_admin');
        $this->actingAs($manager, 'web')->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $rider->id])->assertStatus(409);
        $this->post(route('deliveries.dispatch', $delivery))->assertRedirect()->assertSessionHas('success');
        $staff = $this->identity('logistics_staff');
        $staff->update(['center_id' => $destination->id]);
        $this->actingAs($staff, 'web')->get(route('sorting-center.index', ['tab' => 'incoming']))->assertOk()->assertSee($delivery->tracking_number);
        $this->post(route('deliveries.receive', $delivery), ['center_id' => $destination->id])->assertRedirect()->assertSessionHas('success');
        $this->assertSame($destination->id, $delivery->fresh()->center_id);
        $this->assertSame('received', $delivery->fresh()->parcel_status);
        $this->assertNull($delivery->fresh()->sorted_at);
        $this->assertDatabaseHas('delivery_status_logs', ['delivery_id' => $delivery->id, 'status' => 'dispatched']);
        $staff->update(['center_id' => $other->id]);
        $this->actingAs($staff->fresh(), 'web')->patch(route('deliveries.scan', $delivery))->assertForbidden();
    }

    public function test_valid_delivery_completion_rolls_back_order_proof_and_cash_when_ledger_fails(): void
    {
        $rider = $this->rider($this->center());
        [$order] = $this->order('out_for_delivery');
        $delivery = $this->shipment($order, $rider, 'out_for_delivery');
        $this->mock(\App\Http\Controllers\Logistics\TransactionController::class, function ($mock) {
            $mock->shouldReceive('storeForDelivery')->once()->andThrow(new \RuntimeException('Simulated ledger outage'));
        });
        Sanctum::actingAs($rider->user);
        $otp = app(DeliveryOtpService::class)->ensureFor($delivery);
        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", ['otp' => $otp, 'amount_received' => 150])->assertStatus(500);
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $this->assertSame('out_for_delivery', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->cod_amount_received);
        $this->assertDatabaseMissing('order_status_histories', ['order_id' => $order->id, 'to_status' => 'delivered']);
        $this->assertDatabaseMissing('delivery_proofs', ['delivery_id' => $delivery->id]);
        $this->assertDatabaseMissing('rider_earnings', ['delivery_id' => $delivery->id]);
    }

    public function test_physical_pickup_prevents_cancellation_even_with_a_stale_order_status(): void
    {
        $rider = $this->rider($this->center());
        [$order, $seller] = $this->order('processing');
        $delivery = $this->shipment($order, $rider, 'picked_up', ['picked_up_at' => now()]);
        $this->actingAs($seller, 'web')->withSession(['active_role' => 'seller'])
            ->post(route('seller.orders.status', $order), ['action' => 'cancel'])->assertSessionHasErrors('order');
        $this->assertSame('processing', $order->fresh()->status);
        $this->assertSame('picked_up', $delivery->fresh()->status);
    }

    public function test_repair_is_previewed_by_default_and_never_restores_stock_twice(): void
    {
        $rider = $this->rider($this->center());
        [$order, , $buyer] = $this->order('cancelled');
        $address = $this->buyerAddress($buyer);
        $order->update(['address_id' => $address->id, 'shipping_address' => $address->toShippingSnapshot()]);
        $delivery = $this->shipment($order, $rider, 'assigned', ['sender_address' => null, 'recipient_address' => null]);
        $product = $order->items->first()->product;
        $stock = $product->stock;
        $this->artisan('orders:repair-fulfillment', ['--order' => $order->id])->assertSuccessful();
        $this->assertSame('assigned', $delivery->fresh()->status);
        $this->assertSame(0, $delivery->items()->count());
        $this->artisan('orders:repair-fulfillment', ['--order' => $order->id, '--apply' => true])->assertSuccessful();
        $this->assertSame('cancelled', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->items()->count());
        $this->assertStringContainsString('Audit Street', $delivery->fresh()->recipient_address);
        $this->artisan('orders:repair-fulfillment', ['--order' => $order->id, '--apply' => true])->assertSuccessful();
        $this->assertSame($stock, $product->fresh()->stock);
        $this->assertSame(1, $delivery->statusLogs()->where('status', 'cancelled')->count());
    }

    public function test_rescheduling_a_rejected_pickup_requires_another_logistics_review(): void
    {
        [$order, $seller] = $this->order('ready_for_delivery');
        $delivery = app(\App\Services\OrderShipmentService::class)->ensure($order);
        $delivery->pickupRequest->update(['status' => 'rejected', 'rejection_reason' => 'Choose a later pickup date.']);
        $tracking = $delivery->tracking_number;
        $this->actingAs($seller, 'web')->withSession(['active_role' => 'seller'])->post(route('seller.orders.pickup', $order), [
            'courier' => 'INVOIZ Logistics', 'pickup_at' => now()->addDay()->toDateString(), 'notes' => 'Rescheduled pickup',
        ])->assertRedirect()->assertSessionHas('success');
        $this->assertSame('pending', $delivery->pickupRequest->fresh()->status);
        $this->assertSame(1, $delivery->pickupRequest()->count());
        $this->assertSame($tracking, $delivery->fresh()->tracking_number);
        $this->assertSame('ready_for_delivery', $order->fresh()->status);
        $this->assertDatabaseHas('delivery_status_logs', ['delivery_id' => $delivery->id, 'status' => 'pickup_resubmitted']);
    }

    public function test_failed_final_leg_can_retry_with_the_same_rider_but_cannot_swap_custody_without_return(): void
    {
        $center = $this->center();
        $rider = $this->rider($center);
        $other = $this->rider($center);
        [$order] = $this->order('out_for_delivery');
        $delivery = $this->shipment($order, $rider, 'delivery_failed', [
            'parcel_status' => 'dispatched', 'received_at' => now()->subHours(3),
            'sorted_at' => now()->subHours(2), 'sorting_center_pickup_at' => now()->subHour(),
            'sorting_center_pickup_rider_id' => $rider->id, 'reattempt_requested_at' => now(),
        ]);
        $this->actingAs($this->identity('logistics_admin'), 'web')->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $other->id])->assertStatus(409);
        $this->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $rider->id])->assertRedirect()->assertSessionHas('success');
        $this->assertNotNull($delivery->fresh()->sorting_center_pickup_at);
        $this->assertNull($delivery->fresh()->reattempt_requested_at);
        Sanctum::actingAs($rider->user);
        $this->patchJson("/api/logistics/rider/deliveries/{$delivery->id}/status", ['status' => 'out_for_delivery'])->assertOk();
    }

    public function test_workload_release_keeps_offline_and_inactive_account_settings(): void
    {
        foreach (['delivering' => 'available', 'inactive' => 'inactive'] as $before => $after) {
            $rider = $this->rider($this->center());
            $rider->update(['status' => $before, 'is_online' => false]);
            [$order, $seller] = $this->order('processing');
            $this->shipment($order, $rider, 'assigned');
            \App\Support\OrderCancellation::bySeller($order, 'Store cancelled', $seller->id);
            $this->assertSame($after, $rider->fresh()->status);
            $this->assertFalse($rider->fresh()->is_online);
        }
    }
}

