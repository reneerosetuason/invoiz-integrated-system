<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\RiderApplication;
use App\Models\ServiceArea;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Hub Transfer creation: fleet eligibility feedback and next-hop routing.
 *
 * A transfer leg is routed to a next-hop hub (which may be an intermediate
 * hub), while the parcel's final destination stays what the buyer's service
 * area needs it to be.
 */
class HubTransferNextHopCreationTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function company(string $prefix = 'Next Hop Co'): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => $prefix.' '.uniqid().' '.(++$this->seq),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company, string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => $name.' '.uniqid(),
            'code' => 'HUB-'.rand(1000, 9999),
            'address' => '1 Next Hop Street',
            'city' => 'Manila',
            'province' => 'Metro Manila',
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

    private function staff(LogisticsCompany $company, LogisticsCenter $center): User
    {
        return User::create([
            'name' => 'Next Hop Hub Staff',
            'email' => 'next-hop-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'staff',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
            'center_id' => $center->id,
        ]);
    }

    private function truck(LogisticsCompany $company): LogisticsTruck
    {
        return LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'ownership_type' => 'company',
            'plate_number' => 'NH-'.rand(1000, 9999).'-'.uniqid(),
            'vehicle_type' => 'truck',
            'model' => 'Isuzu Forward',
            'parcel_capacity' => 100,
            'capacity_kg' => 1500.00,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);
    }

    private function driver(LogisticsCompany $company, LogisticsTruck $truck, string $status = 'available', ?string $licenseExpiry = null): HubTransportDriver
    {
        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck->id,
            'name' => 'Next Hop Driver '.uniqid(),
            'email' => 'next-hop-driver-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'license_number' => 'N01-'.rand(100000, 999999),
            'status' => $status,
            'is_online' => true,
        ]);

        RiderApplication::create([
            'name' => $driver->name,
            'email' => $driver->email,
            'phone' => '09171234567',
            'vehicle_type' => 'truck',
            'application_type' => 'hub_transport_driver',
            'license_number' => $driver->license_number,
            'license_expiry' => $licenseExpiry ?? now()->addYear()->format('Y-m-d'),
            'status' => 'approved',
            'hub_transport_driver_id' => $driver->id,
            'logistics_truck_id' => $truck->id,
            'reviewed_at' => now(),
            'provisioned_at' => now(),
        ]);

        return $driver;
    }

    /** @return array{0: LogisticsTruck, 1: HubTransportDriver} */
    private function fleet(LogisticsCompany $company): array
    {
        $truck = $this->truck($company);

        return [$truck, $this->driver($company, $truck)];
    }

    private function delivery(LogisticsCenter $origin, LogisticsCenter $finalDestination, ?ServiceArea $area = null, float $weight = 3.0): Delivery
    {
        return Delivery::create([
            'tracking_number' => 'NH-'.rand(100000, 999999).'-'.substr(uniqid(), -4),
            'center_id' => $origin->id,
            'destination_center_id' => $finalDestination->id,
            'service_area_id' => $area?->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'sorted',
            'weight' => $weight,
            'recipient_name' => 'Next Hop Customer',
            'recipient_phone' => '0917'.rand(1000000, 9999999),
            'recipient_address' => 'Customer Street',
            'recipient_city' => $finalDestination->city,
        ]);
    }

    private function transferPayload(LogisticsCenter $origin, LogisticsCenter $nextHop, LogisticsTruck $truck, HubTransportDriver $driver, Delivery $delivery): array
    {
        return [
            'origin_center_id' => $origin->id,
            'destination_center_id' => $nextHop->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$delivery->id],
        ];
    }

    private function moveLeg(LogisticsCompany $company, LogisticsCenter $origin, LogisticsCenter $nextHop, Delivery $delivery, LogisticsTruck $truck, HubTransportDriver $driver): HubTransfer
    {
        $originStaff = $this->staff($company, $origin);
        $destinationStaff = $this->staff($company, $nextHop);

        $this->actingAs($originStaff)
            ->post(route('hub-transfers.store'), $this->transferPayload($origin, $nextHop, $truck, $driver, $delivery))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $transfer = HubTransfer::latest('id')->firstOrFail();

        $this->post(route('hub-transfers.load', $transfer), ['tracking_number' => $delivery->tracking_number])
            ->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.dispatch', $transfer))->assertSessionHasNoErrors();

        $this->actingAs($destinationStaff);
        $this->post(route('hub-transfers.arrive', $transfer))->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.receive', $transfer), [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ])->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.complete', $transfer))->assertSessionHasNoErrors();

        return $transfer->fresh();
    }

    public function test_fleet_dropdowns_are_enabled_when_a_free_pair_exists(): void
    {
        $company = $this->company();
        $hub = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $hub);
        [$truck, $driver] = $this->fleet($company);

        $response = $this->actingAs($staff)->get(route('hub-transfers.create', [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hub->id,
        ]));

        $response->assertOk();
        $response->assertSee('<select name="truck_id" id="truck-select" required class="select" >', false);
        $response->assertSee('<select name="driver_id" id="driver-select" required class="select" >', false);
        $response->assertSee($truck->plate_number);
        $response->assertSee($driver->name);
        $response->assertDontSee('No eligible truck and driver pair is free right now');
    }

    public function test_blocked_fleet_explains_each_unavailable_truck(): void
    {
        $company = $this->company();
        $hubA = $this->center($company, 'Calamba Hub');
        $hubB = $this->center($company, 'Pila Hub');
        $staff = $this->staff($company, $hubA);

        [$busyTruck, $busyDriver] = $this->fleet($company);
        HubTransfer::create([
            'reference' => 'HT-TEST-'.uniqid(),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $busyTruck->id,
            'driver_id' => $busyDriver->id,
            'status' => 'loading',
            'created_by' => $staff->id,
        ]);
        $unpairedTruck = $this->truck($company);

        $response = $this->actingAs($staff)->get(route('hub-transfers.create', [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
        ]));

        $response->assertOk();
        $response->assertSee('No eligible truck and driver pair is free right now');
        $response->assertSee('already on an active hub transfer');
        $response->assertSee('No Hub Transport Driver assigned');
        $response->assertSee($busyTruck->plate_number);
        $response->assertSee($unpairedTruck->plate_number);
        $response->assertSee('<select name="truck_id" id="truck-select" required class="select" disabled>', false);
    }

    public function test_truck_with_invalid_driver_license_is_not_offered(): void
    {
        $company = $this->company();
        $hub = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $hub);
        $truck = $this->truck($company);
        $this->driver($company, $truck, 'available', now()->subDay()->format('Y-m-d'));

        $response = $this->actingAs($staff)->get(route('hub-transfers.create', [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hub->id,
        ]));

        $response->assertOk();
        $response->assertSee('Driver license is not approved or has expired');
        $response->assertSee('<select name="driver_id" id="driver-select" required class="select" disabled>', false);
    }

    public function test_create_page_offers_intermediate_hubs_and_labels_the_final_destination(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $intermediate = $this->center($company, 'Manila Transfer Hub');
        $pila = $this->center($company, 'Pila Hub');
        $staff = $this->staff($company, $calamba);
        $this->fleet($company);

        $this->delivery($calamba, $pila);

        $response = $this->actingAs($staff)->get(route('hub-transfers.create', [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $calamba->id,
        ]));

        $response->assertOk();
        $response->assertSee('Next-Hop Destination Hub (this leg)');
        $response->assertSee('Final destination: '.$pila->name);
        $response->assertSee('does not change the final destination of the parcels');

        preg_match('/<select name="destination_center_id".*?<\/select>/s', $response->getContent(), $matches);
        $this->assertNotEmpty($matches, 'Destination select was not rendered.');
        $this->assertStringContainsString('value="'.$intermediate->id.'"', $matches[0]);
        $this->assertStringContainsString('value="'.$pila->id.'"', $matches[0]);
        $this->assertStringNotContainsString('value="'.$calamba->id.'"', $matches[0]);
    }

    public function test_transfer_leg_can_end_at_an_intermediate_hub_without_changing_final_destination(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $intermediate = $this->center($company, 'Manila Transfer Hub');
        $pila = $this->center($company, 'Pila Hub');
        $pilaArea = $this->area($pila, 'Pila');
        $staff = $this->staff($company, $calamba);
        [$truck, $driver] = $this->fleet($company);

        $delivery = $this->delivery($calamba, $pila, $pilaArea);

        $this->actingAs($staff)
            ->post(route('hub-transfers.store'), $this->transferPayload($calamba, $intermediate, $truck, $driver, $delivery))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $transfer = HubTransfer::latest('id')->firstOrFail();
        $this->assertSame($calamba->id, (int) $transfer->origin_center_id);
        $this->assertSame($intermediate->id, (int) $transfer->destination_center_id);

        $delivery->refresh();
        $this->assertSame($calamba->id, (int) $delivery->center_id);
        $this->assertSame($pila->id, (int) $delivery->destination_center_id);
        $this->assertSame($pilaArea->id, (int) $delivery->service_area_id);
    }

    public function test_final_destination_survives_a_complete_multi_hop_journey(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $intermediate = $this->center($company, 'Manila Transfer Hub');
        $pila = $this->center($company, 'Pila Hub');
        $pilaArea = $this->area($pila, 'Pila');
        $intermediateStaff = $this->staff($company, $intermediate);
        [$truckA, $driverA] = $this->fleet($company);
        [$truckB, $driverB] = $this->fleet($company);

        $delivery = $this->delivery($calamba, $pila, $pilaArea);

        $legOne = $this->moveLeg($company, $calamba, $intermediate, $delivery, $truckA, $driverA);

        $delivery->refresh();
        $this->assertSame($intermediate->id, (int) $delivery->center_id);
        $this->assertSame('received', $delivery->parcel_status);
        $this->assertSame($pila->id, (int) $delivery->destination_center_id);
        $this->assertSame($pilaArea->id, (int) $delivery->service_area_id);
        $this->assertSame('received', HubTransferParcel::where('hub_transfer_id', $legOne->id)->sole()->receiving_status);

        // The intermediate hub must receive, scan and sort before another leg.
        $this->actingAs($intermediateStaff)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($intermediate, $pila, $truckB, $driverB, $delivery))
            ->assertStatus(422);

        $this->actingAs($intermediateStaff);
        $this->patch(route('deliveries.scan', $delivery))->assertSessionHasNoErrors();
        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $pila->id,
            'service_area_id' => $pilaArea->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($intermediateStaff)
            ->post(route('hub-transfers.store'), $this->transferPayload($intermediate, $pila, $truckB, $driverB, $delivery->fresh()))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $legTwo = HubTransfer::latest('id')->firstOrFail();
        $this->assertSame($intermediate->id, (int) $legTwo->origin_center_id);
        $this->assertSame($pila->id, (int) $legTwo->destination_center_id);
        $this->assertSame($pila->id, (int) $delivery->fresh()->destination_center_id);
        $this->assertSame($pilaArea->id, (int) $delivery->fresh()->service_area_id);
    }

    public function test_circular_next_hop_is_rejected(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $intermediate = $this->center($company, 'Manila Transfer Hub');
        $pila = $this->center($company, 'Pila Hub');
        $pilaArea = $this->area($pila, 'Pila');
        $intermediateStaff = $this->staff($company, $intermediate);
        [$truckA, $driverA] = $this->fleet($company);
        [$truckB, $driverB] = $this->fleet($company);

        $delivery = $this->delivery($calamba, $pila, $pilaArea);
        $this->moveLeg($company, $calamba, $intermediate, $delivery, $truckA, $driverA);

        $this->actingAs($intermediateStaff);
        $this->patch(route('deliveries.scan', $delivery))->assertSessionHasNoErrors();
        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $pila->id,
            'service_area_id' => $pilaArea->id,
        ])->assertSessionHasNoErrors();

        $this->actingAs($intermediateStaff)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($intermediate, $calamba, $truckB, $driverB, $delivery->fresh()))
            ->assertStatus(422)
            ->assertJsonFragment(['message' => "Parcel {$delivery->tracking_number} has already passed through the selected next-hop hub. Choose a hub it has not visited."]);
    }

    public function test_next_hop_must_be_an_active_hub_in_an_active_company(): void
    {
        $company = $this->company();
        $otherCompany = $this->company('Other Co');
        $inactiveCompany = LogisticsCompany::create([
            'name' => 'Closed Co '.uniqid(),
            'status' => 'inactive',
        ]);
        $calamba = $this->center($company, 'Calamba Hub');
        $pila = $this->center($company, 'Pila Hub');
        $staff = $this->staff($company, $calamba);
        $inactive = $this->center($company, 'Closed Hub');
        $inactive->update(['is_active' => false]);
        $inactiveCompanyHub = $this->center($inactiveCompany, 'Closed Company Hub');
        $foreign = $this->center($otherCompany, 'Foreign Hub');
        [$truck, $driver] = $this->fleet($company);

        $delivery = $this->delivery($calamba, $pila);

        $this->actingAs($staff)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($calamba, $inactive, $truck, $driver, $delivery))
            ->assertStatus(422)
            ->assertJsonValidationErrors('destination_center_id');

        $this->actingAs($staff)
            ->postJson(route('hub-transfers.store'), $this->transferPayload($calamba, $inactiveCompanyHub, $truck, $driver, $delivery))
            ->assertStatus(422)
            ->assertJsonValidationErrors('destination_center_id');

        $this->assertSame(0, HubTransfer::where('logistics_company_id', $company->id)->count());

        // The shared network permits an active hub of another active company
        // as the next hop; the hub's company is resolved server-side.
        $this->actingAs($staff)
            ->post(route('hub-transfers.store'), $this->transferPayload($calamba, $foreign, $truck, $driver, $delivery))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $transfer = HubTransfer::latest('id')->firstOrFail();
        $this->assertSame($company->id, (int) $transfer->logistics_company_id);
        $this->assertSame($foreign->id, (int) $transfer->destination_center_id);
    }
}
