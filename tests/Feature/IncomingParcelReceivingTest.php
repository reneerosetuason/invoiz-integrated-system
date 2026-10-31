<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class IncomingParcelReceivingTest extends TestCase
{
    use DatabaseTransactions;

    private function company(string $name): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => $name.' '.bin2hex(random_bytes(3)),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company, string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name.' '.bin2hex(random_bytes(3)),
            'address' => '1 Test Road',
            'city' => 'Test City',
            'province' => 'Metro Manila',
            'is_active' => true,
            'logistics_company_id' => $company->id,
        ]);
    }

    private function staff(LogisticsCompany $company, LogisticsCenter $center): User
    {
        return User::create([
            'name' => 'Hub Staff',
            'email' => 'staff-'.bin2hex(random_bytes(6)).'@test.local',
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

    private function order(string $status = 'ready_for_delivery'): Order
    {
        $user = User::create([
            'name' => 'Test Buyer',
            'email' => 'buyer-'.bin2hex(random_bytes(6)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'status' => 'active',
        ]);

        return Order::create([
            'buyer_id' => $user->id,
            'seller_id' => 1,
            'status' => $status,
            'total_amount' => 500,
        ]);
    }

    private function delivery(LogisticsCenter $center, array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
            'tracking_number' => 'TRK-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
            'sender_name' => 'Test Sender',
            'recipient_name' => 'Test Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'center_id' => $center->id,
            'received_at' => null,
        ], $extra));
    }

    /**
     * 1. An eligible incoming parcel can be received.
     */
    public function test_eligible_incoming_parcel_can_be_received(): void
    {
        $company = $this->company('Receiving Co');
        $center = $this->center($company, 'Receiving Hub');
        $staff = $this->staff($company, $center);

        $delivery = $this->delivery($center);

        $this->actingAs($staff)
            ->post(route('deliveries.receive', $delivery), ['center_id' => $center->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Parcel received and logged.');

        $delivery->refresh();
        $this->assertSame('received', $delivery->parcel_status);
        $this->assertNotNull($delivery->received_at);
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'received',
        ]);
    }

    /**
     * 2. An order marked delivered does not incorrectly block a valid parcel receipt.
     */
    public function test_order_marked_delivered_does_not_incorrectly_block_valid_parcel_receipt(): void
    {
        $company = $this->company('Delivered Order Co');
        $center = $this->center($company, 'Delivered Order Hub');
        $staff = $this->staff($company, $center);

        $order = $this->order('delivered');
        $delivery = $this->delivery($center, [
            'order_id' => $order->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
        ]);

        $this->actingAs($staff)
            ->post(route('deliveries.receive', $delivery), ['center_id' => $center->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Parcel received and logged.');

        $delivery->refresh();
        $this->assertSame('received', $delivery->parcel_status);
        $this->assertNotNull($delivery->received_at);
        // Order remains delivered
        $this->assertSame('delivered', $order->fresh()->status);
    }

    /**
     * 3. A genuinely completed transfer cannot be received twice.
     */
    public function test_genuinely_completed_transfer_cannot_be_received_twice(): void
    {
        $company = $this->company('Completed Transfer Co');
        $origin = $this->center($company, 'Transfer Origin');
        $destination = $this->center($company, 'Transfer Destination');
        $staff = $this->staff($company, $destination);

        $transfer = HubTransfer::create([
            'reference' => 'HT-TEST-'.strtoupper(bin2hex(random_bytes(4))),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => 'received',
            'received_at' => now(),
        ]);

        $delivery = $this->delivery($destination, [
            'parcel_status' => 'received',
            'received_at' => now(),
        ]);

        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'received',
            'received_at' => now(),
        ]);

        // Attempting to receive against completed transfer fails
        $this->actingAs($staff)
            ->post(route('hub-transfers.receive', $transfer), [
                'tracking_number' => $delivery->tracking_number,
                'condition' => 'good',
            ])
            ->assertStatus(409);

        // Active transfer with already received manifest entry is rejected as duplicate
        $activeTransfer = HubTransfer::create([
            'reference' => 'HT-ACT-'.strtoupper(bin2hex(random_bytes(4))),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => 'arrived',
        ]);
        $activeDelivery = $this->delivery($origin, [
            'destination_center_id' => $destination->id,
            'parcel_status' => 'dispatched',
        ]);
        HubTransferParcel::create([
            'hub_transfer_id' => $activeTransfer->id,
            'delivery_id' => $activeDelivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'received',
            'received_at' => now(),
        ]);
        $this->actingAs($staff)
            ->post(route('hub-transfers.receive', $activeTransfer), [
                'tracking_number' => $activeDelivery->tracking_number,
                'condition' => 'good',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'This parcel has already been accounted for.');

        // Attempting to receive an already-received delivery directly returns duplicate error
        $this->actingAs($staff)
            ->post(route('deliveries.receive', $delivery), ['center_id' => $destination->id])
            ->assertRedirect()
            ->assertSessionHas('error', 'This parcel has already been received.');
    }

    /**
     * 4. Receiving at a hub does not mark the buyer's order delivered.
     */
    public function test_receiving_at_a_hub_does_not_mark_the_buyers_order_delivered(): void
    {
        $company = $this->company('Order Integrity Co');
        $center = $this->center($company, 'Order Hub');
        $staff = $this->staff($company, $center);

        $order = $this->order('ready_for_delivery');
        $delivery = $this->delivery($center, [
            'order_id' => $order->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
        ]);

        $this->actingAs($staff)
            ->post(route('deliveries.receive', $delivery), ['center_id' => $center->id])
            ->assertRedirect()
            ->assertSessionHas('success', 'Parcel received and logged.');

        $delivery->refresh();
        $this->assertSame('received', $delivery->parcel_status);

        // Crucial: buyer's order status must NOT be changed to delivered
        $this->assertSame('ready_for_delivery', $order->fresh()->status);
    }

    /**
     * 5. Unauthorized hubs cannot receive another hub's parcels.
     */
    public function test_unauthorized_hubs_cannot_receive_another_hubs_parcels(): void
    {
        $company = $this->company('Isolation Co');
        $centerA = $this->center($company, 'Hub A');
        $centerB = $this->center($company, 'Hub B');

        $staffB = $this->staff($company, $centerB);
        $deliveryA = $this->delivery($centerA);

        // Staff B attempts to receive parcel belonging to Hub A
        $this->actingAs($staffB)
            ->post(route('deliveries.receive', $deliveryA), ['center_id' => $centerA->id])
            ->assertForbidden();

        // Staff B attempts to receive Hub A's parcel at Hub B without a valid transfer
        $this->actingAs($staffB)
            ->post(route('deliveries.receive', $deliveryA), ['center_id' => $centerB->id])
            ->assertForbidden();

        $deliveryA->refresh();
        $this->assertSame('pending_arrival', $deliveryA->parcel_status);
        $this->assertNull($deliveryA->received_at);
    }
}
