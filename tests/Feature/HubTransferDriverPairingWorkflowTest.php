<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\RiderApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the Fleet Management -> Hub Transfer -> Rider App hand-off:
 * a driver paired with a four-wheel truck in Fleet Management must be
 * recognised by Hub Transfer, and transfer creation must never double-book
 * a truck or driver or accept a mismatched pair.
 */
class HubTransferDriverPairingWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function company(): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => 'Pairing Co '.uniqid().' '.(++$this->seq),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company, string $name = 'Pairing Hub'): LogisticsCenter
    {
        return LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => $name.' '.uniqid(),
            'code' => 'HUB-'.rand(1000, 9999),
            'address' => '123 Hub Street',
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'is_active' => true,
        ]);
    }

    private function manager(LogisticsCompany $company): User
    {
        return User::create([
            'phone' => '09171234567',
            'name' => 'Pairing Manager',
            'email' => 'pairing-mgr-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
        ]);
    }

    private function truck(LogisticsCompany $company, ?string $plate = null): LogisticsTruck
    {
        return LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'ownership_type' => 'company',
            'plate_number' => $plate ?: 'TRK-'.rand(1000, 9999).'-'.uniqid(),
            'vehicle_type' => 'light truck',
            'model' => 'Isuzu Forward',
            'parcel_capacity' => 100,
            'capacity_kg' => 1500.00,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);
    }

    private function driver(
        LogisticsCompany $company,
        ?LogisticsTruck $truck = null,
        string $status = 'available',
        string $applicationStatus = 'approved',
    ): HubTransportDriver {
        $email = 'pairing-driver-'.uniqid().'@test.local';
        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck?->id,
            'name' => 'Pairing Driver '.uniqid(),
            'email' => $email,
            'password' => Hash::make('password123'),
            'license_number' => 'N01-'.rand(100000, 999999),
            'status' => $status,
        ]);

        RiderApplication::create([
            'name' => $driver->name,
            'email' => $email,
            'phone' => '09171234567',
            'vehicle_type' => 'truck',
            'application_type' => 'hub_transport_driver',
            'license_number' => $driver->license_number,
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'status' => $applicationStatus,
            'hub_transport_driver_id' => $driver->id,
            'logistics_truck_id' => $truck?->id,
            'reviewed_at' => now(),
            'provisioned_at' => now(),
        ]);

        return $driver;
    }

    private function parcel(LogisticsCenter $origin, LogisticsCenter $destination): Delivery
    {
        return Delivery::create([
            'tracking_number' => 'TRK-'.rand(100000, 999999).'-'.uniqid(),
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'sorted',
            'weight' => 5.0,
            'recipient_name' => 'Pairing Customer',
            'recipient_phone' => '09171234567',
            'recipient_address' => 'Customer Street',
            'recipient_city' => $destination->city,
        ]);
    }

    private function pair(User $manager, HubTransportDriver $driver, LogisticsTruck $truck): void
    {
        $this->actingAs($manager)
            ->put(route('fleet.drivers.update', $driver), [
                'status' => 'available',
                'logistics_truck_id' => $truck->id,
            ])
            ->assertRedirect();
    }

    private function createTransfer(User $manager, LogisticsCompany $company, LogisticsCenter $origin, LogisticsCenter $destination, LogisticsTruck $truck, HubTransportDriver $driver, Delivery $parcel): HubTransfer
    {
        $this->actingAs($manager)->post(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'notes' => 'Load at the main dock and call the destination hub on arrival.',
            'planned_dispatch_at' => now()->addHour()->format('Y-m-d H:i:s'),
            'parcels' => [$parcel->id],
        ])->assertRedirect();

        return HubTransfer::latest('id')->firstOrFail();
    }

    public function test_fleet_pairing_exposes_assigned_truck_and_driver_to_hub_transfer(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Origin');
        $truck = $this->truck($company, 'PAIR-TRK-01');
        $driver = $this->driver($company);

        $this->pair($manager, $driver, $truck);

        $this->assertSame('assigned', $truck->fresh()->status);

        $this->actingAs($manager)->get(route('hub-transfers.create', [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
        ]))
            ->assertOk()
            ->assertSee('PAIR-TRK-01')
            ->assertSee($driver->name)
            ->assertDontSee('No eligible truck and driver pair is free right now')
            ->assertSee('<select name="truck_id" id="truck-select" required class="select" >', false);
    }

    public function test_hub_transfer_creation_uses_paired_assigned_truck_and_driver(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $truck = $this->truck($company);
        $driver = $this->driver($company);
        $parcel = $this->parcel($origin, $destination);

        $this->pair($manager, $driver, $truck);
        $transfer = $this->createTransfer($manager, $company, $origin, $destination, $truck, $driver, $parcel);

        $this->assertSame('loading', $transfer->status);
        $this->assertSame($truck->id, (int) $transfer->truck_id);
        $this->assertSame($driver->id, (int) $transfer->driver_id);
        $this->assertSame('assigned', $truck->fresh()->status);
        $this->assertSame('assigned', $driver->fresh()->status);
    }

    public function test_hub_transfer_rejects_driver_that_is_not_paired_with_selected_truck(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $pairedTruck = $this->truck($company);
        $driver = $this->driver($company);
        $otherTruck = $this->truck($company);
        $parcel = $this->parcel($origin, $destination);

        $this->pair($manager, $driver, $pairedTruck);

        $response = $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $otherTruck->id,
            'driver_id' => $driver->id,
            'parcels' => [$parcel->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'message' => 'Truck '.$otherTruck->plate_number.' has no assigned Hub Transport Driver. Open Fleet Management and assign an approved driver to this truck first.',
        ]);
        $this->assertSame(0, HubTransfer::where('logistics_company_id', $company->id)->count());
    }

    public function test_hub_transfer_rejects_truck_and_driver_with_an_active_manifest(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $truck = $this->truck($company);
        $driver = $this->driver($company);

        $this->pair($manager, $driver, $truck);
        $this->createTransfer($manager, $company, $origin, $destination, $truck, $driver, $this->parcel($origin, $destination));

        $secondParcel = $this->parcel($origin, $destination);

        $response = $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$secondParcel->id],
        ]);

        $response->assertStatus(409);
        $response->assertJsonFragment(['message' => 'This truck is already assigned to another active hub transfer.']);
        $this->assertSame(1, HubTransfer::where('logistics_company_id', $company->id)->count());
    }

    public function test_create_page_guides_to_fleet_management_when_no_driver_is_paired(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Origin');
        $this->truck($company, 'UNPAIRED-TRK-01');

        $this->actingAs($manager)->get(route('hub-transfers.create', [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
        ]))
            ->assertOk()
            ->assertSee('1 eligible truck has no assigned Hub Transport Driver')
            ->assertSee('No Hub Transport Driver assigned')
            ->assertSee(route('fleet.index', ['logistics_company_id' => $company->id]), false);
    }

    public function test_reassign_accepts_another_paired_truck_before_dispatch(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $firstTruck = $this->truck($company);
        $firstDriver = $this->driver($company);
        $secondTruck = $this->truck($company);
        $secondDriver = $this->driver($company);

        $this->pair($manager, $firstDriver, $firstTruck);
        $this->pair($manager, $secondDriver, $secondTruck);
        $transfer = $this->createTransfer($manager, $company, $origin, $destination, $firstTruck, $firstDriver, $this->parcel($origin, $destination));

        $this->actingAs($manager)
            ->post(route('hub-transfers.reassign', $transfer), ['truck_id' => $secondTruck->id])
            ->assertRedirect();

        $transfer->refresh();
        $this->assertSame($secondTruck->id, (int) $transfer->truck_id);
        $this->assertSame($secondDriver->id, (int) $transfer->driver_id);
        $this->assertSame('assigned', $secondTruck->fresh()->status);
        $this->assertSame('assigned', $secondDriver->fresh()->status);
        $this->assertSame('available', $firstTruck->fresh()->status);
        $this->assertSame('available', $firstDriver->fresh()->status);
    }

    public function test_fleet_management_blocks_inactive_or_unapproved_driver_assignment(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $truck = $this->truck($company);
        $driver = $this->driver($company);
        $pendingDriver = $this->driver($company, null, 'available', 'pending');

        $this->actingAs($manager)->put(route('fleet.drivers.update', $driver), [
            'status' => 'inactive',
            'logistics_truck_id' => $truck->id,
        ])->assertStatus(422);

        $this->actingAs($manager)->put(route('fleet.drivers.update', $pendingDriver), [
            'status' => 'available',
            'logistics_truck_id' => $truck->id,
        ])->assertStatus(422);

        $this->assertNull($driver->fresh()->logistics_truck_id);
        $this->assertNull($pendingDriver->fresh()->logistics_truck_id);
        $this->assertSame('available', $truck->fresh()->status);
    }

    public function test_hub_courier_api_returns_assigned_transfer_and_dispatch_instructions(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $truck = $this->truck($company);
        $driver = $this->driver($company);

        $this->pair($manager, $driver, $truck);
        $transfer = $this->createTransfer($manager, $company, $origin, $destination, $truck, $driver, $this->parcel($origin, $destination));

        $login = $this->postJson('/api/logistics/courier/login', [
            'email' => $driver->email,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('courier.id', $driver->id);

        $token = $login->json('token');

        // Clear the web guard user set by actingAs() so the request is
        // authenticated by the bearer token, like the mobile app.
        $this->app['auth']->forgetGuards();

        $dashboard = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/logistics/courier/dashboard');
        $dashboard->assertOk()
            ->assertJsonPath('active_transfer.reference', $transfer->reference)
            ->assertJsonPath('available_transfers.0.reference', $transfer->reference)
            ->assertJsonPath('available_transfers.0.notes', 'Load at the main dock and call the destination hub on arrival.');
    }
}
