<?php

namespace Tests\Feature;

use App\Http\Controllers\Logistics\DeliveryController;
use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Stage-aware Delivery Rider eligibility plus sequential, multi-leg hub
 * routing. A parcel may pass through any number of hubs; each leg is a real
 * transfer with its own fleet pair and only starts once the previous hub
 * finished receiving, scanning and sorting the parcel.
 */
class StageAwareRiderAssignmentAndMultiHubRoutingTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function company(string $prefix = 'Routing Co'): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => $prefix.' '.uniqid(),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company, string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => $name.' '.uniqid(),
            'address' => '1 Routing Street',
            'city' => 'Routing City',
            'is_active' => true,
        ]);
    }

    private function area(LogisticsCenter $center, string $name): ServiceArea
    {
        return ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => $name,
            'is_active' => true,
        ]);
    }

    private function manager(LogisticsCompany $company): User
    {
        $this->seq++;

        return User::create([
            'name' => 'Routing Manager '.$this->seq,
            'email' => 'routing-manager-'.$this->seq.'-'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
        ]);
    }

    private function rider(?LogisticsCenter $center, ?ServiceArea $area, string $name): Rider
    {
        $this->seq++;

        return Rider::create([
            'center_id' => $center?->id,
            'service_area_id' => $area?->id,
            'name' => $name.' '.$this->seq,
            'email' => 'routing-rider-'.$this->seq.'-'.uniqid().'@test.local',
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'vehicle_capacity_kg' => 50,
            'status' => 'available',
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'is_online' => true,
        ]);
    }

    private function truck(LogisticsCompany $company, string $vehicleType = 'truck', string $status = 'available'): LogisticsTruck
    {
        $this->seq++;

        return LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'ownership_type' => 'company',
            'plate_number' => 'RT-'.$this->seq.'-'.substr(uniqid(), -4),
            'vehicle_type' => $vehicleType,
            'model' => 'Isuzu Forward',
            'parcel_capacity' => 100,
            'capacity_kg' => 1500,
            'status' => $status,
            'verification_status' => 'approved',
        ]);
    }

    private function driver(LogisticsCompany $company, LogisticsTruck $truck, string $status = 'available'): HubTransportDriver
    {
        $this->seq++;

        return HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck->id,
            'name' => 'Routing Driver '.$this->seq,
            'email' => 'routing-driver-'.$this->seq.'-'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'license_number' => 'N01-'.$this->seq.'-'.substr(uniqid(), -4),
            'status' => $status,
            'is_online' => true,
        ]);
    }

    /** @return array{0: LogisticsTruck, 1: HubTransportDriver} */
    private function fleet(LogisticsCompany $company, string $vehicleType = 'truck'): array
    {
        $truck = $this->truck($company, $vehicleType);

        return [$truck, $this->driver($company, $truck)];
    }

    private function order(string $status = 'ready_for_delivery'): Order
    {
        $this->seq++;
        $sellerUser = User::create([
            'name' => 'Routing Seller '.$this->seq,
            'email' => 'routing-seller-'.$this->seq.'-'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'seller',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'store_name' => 'Routing Shop '.$sellerUser->id,
            'status' => 'active',
            'approval_status' => 'approved',
            'vacation_mode' => false,
            'address_line' => 'Sender Street',
            'barangay' => 'Routing Barangay',
            'city' => 'Routing City',
            'province' => 'Routing Province',
        ]);
        $buyer = User::create([
            'name' => 'Routing Buyer '.$this->seq,
            'email' => 'routing-buyer-'.$this->seq.'-'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        return Order::create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'status' => $status,
            'total_amount' => 150,
            'sub_total' => 100,
            'shipping_fee' => 50,
            'payment_status' => 'pending',
        ]);
    }

    private function delivery(array $overrides = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'tracking_number' => 'TRK-ROUTE-'.str_pad((string) $this->seq, 5, '0', STR_PAD_LEFT).'-'.substr(uniqid(), -4),
            'sender_name' => 'Routing Sender',
            'sender_address' => 'Sender Street',
            'recipient_name' => 'Routing Recipient',
            'recipient_address' => 'Recipient Street',
            'status' => 'waiting_for_rider',
            'parcel_status' => 'sorted',
            'weight' => 2,
        ], $overrides));
    }

    private function approvedPickupRequest(Delivery $delivery, LogisticsCenter $center): PickupRequest
    {
        return PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $center->id,
            'status' => 'approved',
            'requested_at' => now(),
        ]);
    }

    private function transferPayload(LogisticsCenter $origin, LogisticsCenter $destination, LogisticsTruck $truck, HubTransportDriver $driver, Delivery $delivery): array
    {
        return [
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$delivery->id],
        ];
    }

    private function moveLeg(LogisticsCompany $company, LogisticsCenter $origin, LogisticsCenter $destination, Delivery $delivery, ?LogisticsTruck $truck = null, ?HubTransportDriver $driver = null): HubTransfer
    {
        [$defaultTruck, $defaultDriver] = $this->fleet($company);
        $truck ??= $defaultTruck;
        $driver ??= $defaultDriver;

        $this->actingAs($this->manager($company))
            ->post(route('hub-transfers.store'), $this->transferPayload($origin, $destination, $truck, $driver, $delivery))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $transfer = HubTransfer::latest('id')->firstOrFail();

        $this->post(route('hub-transfers.load', $transfer), ['tracking_number' => $delivery->tracking_number])
            ->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.dispatch', $transfer))->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.arrive', $transfer))->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.receive', $transfer), [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ])->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.complete', $transfer))->assertSessionHasNoErrors();

        return $transfer->fresh();
    }

    public function test_first_mile_uses_seller_pickup_scope_and_last_mile_uses_buyer_hub(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $pila = $this->center($company, 'Pila Hub');
        $calambaArea = $this->area($calamba, 'Calamba');
        $pilaArea = $this->area($pila, 'Pila');
        $otherCalambaArea = $this->area($calamba, 'Los Banos');

        $manager = $this->manager($company);
        $pickupRider = $this->rider($calamba, $calambaArea, 'John Criz');
        $pilaRider = $this->rider($pila, $pilaArea, 'Pila Rider');
        $otherAreaRider = $this->rider($calamba, $otherCalambaArea, 'Calamba Other Area Rider');
        [$truck, $driver] = $this->fleet($company);

        $order = $this->order();
        $delivery = $this->delivery([
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'center_id' => $calamba->id,
            'destination_center_id' => $pila->id,
            'service_area_id' => $pilaArea->id,
            'sender_address' => 'Calamba, Laguna',
            'recipient_address' => 'Pila, Laguna',
            'parcel_status' => 'pending_arrival',
        ]);
        $this->approvedPickupRequest($delivery, $calamba);

        // Even though the stored service area is the buyer's Pila area, the
        // first-mile rider is evaluated against the seller's Calamba pickup.
        $this->actingAs($manager);
        $rows = app(DeliveryController::class)->riderEligibilityFor($delivery);

        $this->assertTrue($rows->firstWhere('rider.id', $pickupRider->id)['eligible']);
        $this->assertSame('Different pickup hub', $rows->firstWhere('rider.id', $pilaRider->id)['reason']);
        $this->assertSame('Different pickup service area', $rows->firstWhere('rider.id', $otherAreaRider->id)['reason']);

        $this->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $pickupRider->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame($pickupRider->id, (int) $delivery->fresh()->rider_id);

        // The pickup rider collects from the seller and records the hub handoff.
        $delivery->update([
            'picked_up_at' => now(),
            'sorting_center_handoff_at' => now(),
            'sorting_center_handoff_rider_id' => $pickupRider->id,
        ]);
        $this->post(route('deliveries.receive', $delivery), ['center_id' => $calamba->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame('received', $delivery->fresh()->parcel_status);

        // Origin hub custody: nobody may take the parcel for last mile yet.
        $rows = app(DeliveryController::class)->riderEligibilityFor($delivery->fresh());
        $this->assertSame('Parcel is at an intermediate hub and still needs transfer to its final destination hub before last-mile assignment.', $rows->firstWhere('rider.id', $pilaRider->id)['reason']);
        $this->get(route('deliveries.show', $delivery))
            ->assertOk()
            ->assertSee('Next step before last-mile assignment')
            ->assertSee('Continue in Hub Transfers')
            ->assertSee($calamba->name)
            ->assertSee($pila->name);

        $this->actingAs($manager);
        $this->patch(route('deliveries.scan', $delivery))->assertSessionHasNoErrors();
        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $pila->id,
            'service_area_id' => $pilaArea->id,
        ])->assertSessionHasNoErrors();

        // Still at Calamba: routed to another hub, so hub custody continues.
        $rows = app(DeliveryController::class)->riderEligibilityFor($delivery->fresh());
        $this->assertSame('Parcel is at an intermediate hub and still needs transfer to its final destination hub before last-mile assignment.', $rows->firstWhere('rider.id', $pilaRider->id)['reason']);

        $transfer = $this->moveLeg($company, $calamba, $pila, $delivery->fresh(), $truck, $driver);
        $delivery->refresh();
        $this->assertSame($pila->id, (int) $delivery->center_id);
        $this->assertSame('received', $delivery->parcel_status);

        // Received at the final hub but not scanned/sorted yet.
        $this->actingAs($manager);
        $rows = app(DeliveryController::class)->riderEligibilityFor($delivery);
        $this->assertSame('Parcel must be scanned and sorted at its destination hub before last-mile assignment.', $rows->firstWhere('rider.id', $pilaRider->id)['reason']);

        $this->patch(route('deliveries.scan', $delivery))->assertSessionHasNoErrors();
        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $pila->id,
            'service_area_id' => $pilaArea->id,
        ])->assertSessionHasNoErrors();

        $rows = app(DeliveryController::class)->riderEligibilityFor($delivery->fresh());
        $this->assertTrue($rows->firstWhere('rider.id', $pilaRider->id)['eligible']);
        $this->assertSame('Different destination hub', $rows->firstWhere('rider.id', $pickupRider->id)['reason']);

        $this->actingAs($manager)
            ->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $pickupRider->id])
            ->assertRedirect()
            ->assertSessionHasErrors('rider_id');

        $this->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $pilaRider->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $this->assertSame($pilaRider->id, (int) $delivery->fresh()->rider_id);
        $this->assertSame(1, HubTransfer::where('origin_center_id', $calamba->id)
            ->where('destination_center_id', $pila->id)->count());
        $this->assertSame('received', $transfer->status);
    }

    public function test_parcel_can_cross_multiple_hubs_with_a_new_fleet_pair_per_leg(): void
    {
        $company = $this->company('Multi Hop Co');
        $hubA = $this->center($company, 'Hub A');
        $hubB = $this->center($company, 'Hub B');
        $hubC = $this->center($company, 'Hub C');
        $areaA = $this->area($hubA, 'Area A');
        $areaB = $this->area($hubB, 'Area B');
        $areaC = $this->area($hubC, 'Area C');

        $manager = $this->manager($company);
        $lastMileRider = $this->rider($hubC, $areaC, 'Hub C Rider');
        $foreignRider = $this->rider($hubA, $areaA, 'Hub A Rider');
        [$truckA, $driverA] = $this->fleet($company);
        [$truckB, $driverB] = $this->fleet($company);

        $delivery = $this->delivery([
            'center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'service_area_id' => $areaB->id,
            'sender_address' => 'Area A',
            'recipient_address' => 'Area C',
            'parcel_status' => 'sorted',
            'sorted_at' => now(),
        ]);

        // Leg 1: A -> B.
        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->transferPayload($hubA, $hubB, $truckA, $driverA, $delivery))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $legOne = HubTransfer::latest('id')->firstOrFail();

        // A second active manifest for the same parcel is rejected.
        $this->actingAs($manager)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($hubA, $hubB, $truckB, $driverB, $delivery))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Parcel {$delivery->tracking_number} is already included in an active transfer."]);

        $this->post(route('hub-transfers.load', $legOne), ['tracking_number' => $delivery->tracking_number])
            ->assertSessionHasNoErrors();

        // Dispatch is refused while the origin hub has not sorted the parcel.
        $delivery->update(['parcel_status' => 'received']);
        $this->post(route('hub-transfers.dispatch', $legOne))->assertStatus(409);
        $delivery->update(['parcel_status' => 'sorted']);

        $this->post(route('hub-transfers.dispatch', $legOne))->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.arrive', $legOne))->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.receive', $legOne), [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ])->assertSessionHasNoErrors();

        $delivery->refresh();
        $this->assertSame($hubB->id, (int) $delivery->center_id);
        $this->assertSame('received', $delivery->parcel_status);
        $this->assertSame('received', HubTransferParcel::where('hub_transfer_id', $legOne->id)->sole()->receiving_status);

        // Intermediate hub: a next leg cannot be created before the hub
        // receives, scans and sorts the parcel toward the following hub.
        $this->actingAs($manager)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($hubB, $hubC, $truckB, $driverB, $delivery))
            ->assertStatus(422);

        $this->post(route('hub-transfers.complete', $legOne))->assertSessionHasNoErrors();
        $this->patch(route('deliveries.scan', $delivery))->assertSessionHasNoErrors();
        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $hubC->id,
            'service_area_id' => $areaC->id,
        ])->assertSessionHasNoErrors();

        // Leg 2: B -> C with a different truck and driver.
        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->transferPayload($hubB, $hubC, $truckB, $driverB, $delivery->fresh()))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
        $legTwo = HubTransfer::latest('id')->firstOrFail();

        $this->assertSame($hubB->id, (int) $legTwo->origin_center_id);
        $this->assertSame($hubC->id, (int) $legTwo->destination_center_id);
        $this->assertSame($truckB->id, (int) $legTwo->truck_id);
        $this->assertSame($driverB->id, (int) $legTwo->driver_id);
        $this->assertNotSame((int) $legOne->truck_id, (int) $legTwo->truck_id);
        $this->assertNotSame((int) $legOne->driver_id, (int) $legTwo->driver_id);
        $this->assertSame(1, HubTransferParcel::where('hub_transfer_id', $legTwo->id)->count());

        $this->post(route('hub-transfers.load', $legTwo), ['tracking_number' => $delivery->tracking_number])
            ->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.dispatch', $legTwo))->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.arrive', $legTwo))->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.receive', $legTwo), [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ])->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.complete', $legTwo))->assertSessionHasNoErrors();

        $delivery->refresh();
        $this->assertSame($hubC->id, (int) $delivery->center_id);
        $this->assertSame('received', $delivery->parcel_status);
        $this->assertSame(2, HubTransfer::whereIn('id', [$legOne->id, $legTwo->id])->count());
        $this->assertSame(1, HubTransferParcel::where('delivery_id', $delivery->id)->where('hub_transfer_id', $legTwo->id)->count());

        // The buyer's final hub processes the parcel before a last-mile rider
        // may take custody, then only a Hub C rider is eligible.
        $this->actingAs($manager);
        $this->patch(route('deliveries.scan', $delivery))->assertSessionHasNoErrors();
        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $hubC->id,
            'service_area_id' => $areaC->id,
        ])->assertSessionHasNoErrors();

        $rows = app(DeliveryController::class)->riderEligibilityFor($delivery->fresh());
        $this->assertTrue($rows->firstWhere('rider.id', $lastMileRider->id)['eligible']);
        $this->assertSame('Different destination hub', $rows->firstWhere('rider.id', $foreignRider->id)['reason']);

        $this->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $foreignRider->id])
            ->assertRedirect()
            ->assertSessionHasErrors('rider_id');
        $this->post(route('deliveries.assign-rider', $delivery), ['rider_id' => $lastMileRider->id])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        // Tracking history records both fleet legs, receipts and hub sorting.
        $this->assertSame(2, DeliveryStatusLog::where('delivery_id', $delivery->id)
            ->where('status', 'hub_transfer_dispatched')->count());
        $this->assertGreaterThanOrEqual(2, DeliveryStatusLog::where('delivery_id', $delivery->id)
            ->where('status', 'received')->count());
        $this->assertGreaterThanOrEqual(2, DeliveryStatusLog::where('delivery_id', $delivery->id)
            ->where('status', 'sorted')->count());
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'hub_transfer_dispatched',
            'notes' => 'Dispatched from origin hub via transfer '.$legOne->reference.'.',
        ]);
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'hub_transfer_dispatched',
            'notes' => 'Dispatched from origin hub via transfer '.$legTwo->reference.'.',
        ]);
        $this->assertSame(2, HubTransfer::whereHas('parcels', fn ($query) => $query->where('delivery_id', $delivery->id))->count());
    }

    public function test_transfer_creation_rejects_cross_company_unavailable_and_wrong_vehicle_fleet(): void
    {
        $companyA = $this->company('Company A');
        $companyB = $this->company('Company B');
        $origin = $this->center($companyA, 'A Origin');
        $destination = $this->center($companyA, 'A Destination');
        $foreignHub = $this->center($companyB, 'B Hub');
        $manager = $this->manager($companyA);

        $delivery = $this->delivery([
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'parcel_status' => 'sorted',
        ]);

        $foreignTruck = $this->truck($companyB);
        $foreignDriver = $this->driver($companyB, $foreignTruck);

        $this->actingAs($manager)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($origin, $destination, $foreignTruck, $foreignDriver, $delivery))
            ->assertStatus(422);

        $localTruck = $this->truck($companyA);
        $inactiveDriver = $this->driver($companyA, $localTruck, 'inactive');
        $this->actingAs($manager)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($origin, $destination, $localTruck, $inactiveDriver, $delivery))
            ->assertStatus(422);

        $twoWheelTruck = $this->truck($companyA, 'motorcycle');
        $twoWheelDriver = $this->driver($companyA, $twoWheelTruck);
        $this->actingAs($manager)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($origin, $destination, $twoWheelTruck, $twoWheelDriver, $delivery))
            ->assertStatus(422);

        $this->actingAs($manager)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($origin, $foreignHub, $localTruck, $inactiveDriver, $delivery))
            ->assertStatus(422);

        $this->assertSame(0, HubTransfer::where('logistics_company_id', $companyA->id)->count());
        $this->assertSame('sorted', $delivery->fresh()->parcel_status);
    }
}
