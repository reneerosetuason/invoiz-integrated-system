<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\User;
use App\Models\VehicleMaintenanceRecord;
use App\Services\HubTransferService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HubTransferBulkSortingAndFleetRepairTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function company(string $prefix = 'Test Co'): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => $prefix.' '.uniqid().' '.(++$this->seq),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company, string $name = 'Test Hub'): LogisticsCenter
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
            'name' => 'Manager User',
            'email' => 'mgr-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
        ]);
    }

    private function truck(LogisticsCompany $company, string $status = 'available', string $ownership = 'company'): LogisticsTruck
    {
        return LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'ownership_type' => $ownership,
            'plate_number' => 'TRK-'.rand(1000, 9999).'-'.uniqid(),
            'vehicle_type' => 'truck',
            'model' => 'Isuzu Forward',
            'parcel_capacity' => 100,
            'capacity_kg' => 1500.00,
            'status' => $status,
            'verification_status' => 'approved',
        ]);
    }

    private function driver(LogisticsCompany $company, LogisticsTruck $truck, string $status = 'available'): HubTransportDriver
    {
        $user = User::create([
            'name' => 'Driver User',
            'email' => 'driver-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
        ]);

        return HubTransportDriver::create([
            'user_id' => $user->id,
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck->id,
            'name' => 'Transport Driver '.uniqid(),
            'email' => $user->email,
            'password' => Hash::make('password123'),
            'license_number' => 'N01-'.rand(100000, 999999),
            'status' => $status,
        ]);
    }

    private function parcel(LogisticsCenter $origin, LogisticsCenter $dest, string $parcelStatus = 'received', ?float $weight = 5.0): Delivery
    {
        return Delivery::create([
            'tracking_number' => 'TRK-'.rand(100000, 999999).'-'.uniqid(),
            'center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => $parcelStatus,
            'weight' => $weight,
            'recipient_name' => 'Customer '.uniqid(),
            'recipient_phone' => '0917'.rand(1000000, 9999999),
            'recipient_address' => 'Customer Street',
            'recipient_city' => $dest->city,
        ]);
    }

    public function test_parcels_grouped_by_destination_on_create_page(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $hubA = $this->center($company, 'Manila Hub');
        $hubB = $this->center($company, 'Cebu Hub');
        $hubC = $this->center($company, 'Davao Hub');

        $truck = $this->truck($company);
        $this->driver($company, $truck);

        $p1 = $this->parcel($hubA, $hubB, 'received', 4.0);
        $p2 = $this->parcel($hubA, $hubB, 'sorted', 6.0);
        $p3 = $this->parcel($hubA, $hubC, 'received', 8.5);

        $response = $this->actingAs($manager)->get(route('hub-transfers.create', [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
        ]));

        $response->assertOk();
        $response->assertSee($hubB->name);
        $response->assertSee($hubC->name);
        $response->assertSee($p1->tracking_number);
        $response->assertSee($p2->tracking_number);
        $response->assertSee($p3->tracking_number);
        $response->assertSee('Select All');
    }

    public function test_different_destinations_cannot_be_combined_into_one_transfer(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $hubA = $this->center($company, 'Origin Hub');
        $hubB = $this->center($company, 'Destination B');
        $hubC = $this->center($company, 'Destination C');

        $truck = $this->truck($company);
        $driver = $this->driver($company, $truck);

        $p1 = $this->parcel($hubA, $hubB);
        $p2 = $this->parcel($hubA, $hubC); // Different destination!

        $response = $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$p1->id, $p2->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => "Parcel {$p2->tracking_number} is routed to a different hub ({$hubC->name}). All parcels in a single transfer must share the destination hub."]);
    }

    public function test_ineligible_and_already_transferred_parcels_cannot_be_submitted(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $hubA = $this->center($company, 'Origin Hub');
        $hubB = $this->center($company, 'Dest Hub');

        $truck = $this->truck($company);
        $driver = $this->driver($company, $truck);

        // Ineligible: status is delivered
        $pIneligible = $this->parcel($hubA, $hubB, 'received');
        $pIneligible->update(['status' => 'delivered']);

        $response = $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$pIneligible->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => "Parcel {$pIneligible->tracking_number} cannot be transferred."]);
    }

    public function test_backend_validation_prevents_duplicate_active_transfers(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $hubA = $this->center($company, 'Hub A');
        $hubB = $this->center($company, 'Hub B');

        $truck1 = $this->truck($company);
        $driver1 = $this->driver($company, $truck1);

        $truck2 = $this->truck($company);
        $driver2 = $this->driver($company, $truck2);

        $p = $this->parcel($hubA, $hubB);

        // Create first active transfer
        $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $truck1->id,
            'driver_id' => $driver1->id,
            'parcels' => [$p->id],
        ])->assertRedirect();

        // Attempt second active transfer with same parcel
        $response = $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $truck2->id,
            'driver_id' => $driver2->id,
            'parcels' => [$p->id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => "Parcel {$p->tracking_number} is already included in an active transfer."]);
    }

    public function test_correct_manifest_and_parcel_counts_are_saved(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $hubA = $this->center($company, 'Hub A');
        $hubB = $this->center($company, 'Hub B');

        $truck = $this->truck($company);
        $driver = $this->driver($company, $truck);

        $p1 = $this->parcel($hubA, $hubB, 'received', 2.0);
        $p2 = $this->parcel($hubA, $hubB, 'sorted', 3.0);
        $p3 = $this->parcel($hubA, $hubB, 'scanned', 4.0);

        $response = $this->actingAs($manager)->post(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$p1->id, $p2->id, $p3->id],
        ]);

        $response->assertRedirect();

        $transfer = HubTransfer::latest('id')->first();
        $this->assertSame($hubA->id, $transfer->origin_center_id);
        $this->assertSame($hubB->id, $transfer->destination_center_id);
        $this->assertSame('loading', $transfer->status);
        $this->assertSame(3, $transfer->parcels()->count());

        $this->assertDatabaseHas('hub_transfer_parcels', [
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $p1->id,
            'loading_status' => 'pending',
            'receiving_status' => 'pending',
        ]);
    }

    public function test_sequential_transfer_rules_remain_enforced(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $hubA = $this->center($company, 'Hub A');
        $hubB = $this->center($company, 'Hub B');
        $hubC = $this->center($company, 'Hub C');

        $truck1 = $this->truck($company);
        $driver1 = $this->driver($company, $truck1);

        $truck2 = $this->truck($company);
        $driver2 = $this->driver($company, $truck2);

        $parcel = $this->parcel($hubA, $hubC, 'received', 5.0);

        // Leg 1: A -> B
        $this->actingAs($manager)->post(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $truck1->id,
            'driver_id' => $driver1->id,
            'parcels' => [$parcel->id],
        ]);

        $transfer1 = HubTransfer::latest('id')->first();

        // While Transfer 1 is still in loading/in_transit, cannot create Leg 2: B -> C
        $response = $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubB->id,
            'destination_center_id' => $hubC->id,
            'truck_id' => $truck2->id,
            'driver_id' => $driver2->id,
            'parcels' => [$parcel->id],
        ]);

        $response->assertStatus(422);
    }

    public function test_repair_expenses_recorded_without_automatic_earnings_deductions(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $truck = $this->truck($company, 'available', 'driver_owned');
        $driver = $this->driver($company, $truck);

        $origin = $this->center($company);
        $delivery = $this->parcel($origin, $origin);

        // Driver has existing earnings
        $earning = HubTransferEarning::create([
            'delivery_id' => $delivery->id,
            'hub_transfer_id' => null,
            'hub_transport_driver_id' => $driver->id,
            'recipient_role' => HubTransferEarning::ROLE_INTER_HUB_DRIVER,
            'base_amount' => 20.00,
            'vehicle_allowance' => 15.00,
            'vehicle_ownership' => 'driver_owned',
            'amount' => 35.00,
            'earned_on' => now()->toDateString(),
        ]);

        // Record a major maintenance expense of ₱25,000
        $resp = $this->actingAs($manager)->post(route('fleet.maintenance.store'), [
            'logistics_truck_id' => $truck->id,
            'maintenance_type' => 'mechanical_repair',
            'description' => 'Transmission overhaul and clutch replacement',
            'service_provider' => 'Isuzu Authorized Service Hub',
            'estimated_cost' => 25000.00,
            'actual_cost' => 24500.00,
            'payment_responsibility' => 'company',
            'responsibility_notes' => 'Fleet warranty covered maintenance',
            'status' => 'in_repair',
        ]);

        $resp->assertRedirect()->assertSessionHas('success');

        // Verify maintenance record exists
        $record = VehicleMaintenanceRecord::latest('id')->first();
        $this->assertSame($truck->id, $record->logistics_truck_id);
        $this->assertEquals(24500.00, (float) $record->actual_cost);
        $this->assertSame('company', $record->payment_responsibility);

        // Verify driver earnings are completely untouched
        $this->assertSame(1, HubTransferEarning::where('hub_transport_driver_id', $driver->id)->count());
        $this->assertEquals(35.00, (float) $earning->fresh()->amount);
    }

    public function test_driver_owned_and_company_owned_maintenance_responsibilities_recorded_correctly(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);

        $companyTruck = $this->truck($company, 'available', 'company');
        $driverTruck = $this->truck($company, 'available', 'driver_owned');

        // 1. Company truck maintenance
        $resp = $this->actingAs($manager)->post(route('fleet.maintenance.store'), [
            'logistics_truck_id' => $companyTruck->id,
            'maintenance_type' => 'routine_maintenance',
            'description' => 'Oil change and brake pad service',
            'service_provider' => 'Fleet Garage',
            'estimated_cost' => 5000.00,
            'actual_cost' => 4800.00,
            'payment_responsibility' => 'company',
            'status' => 'approved',
        ]);
        $resp->assertRedirect()->assertSessionHas('success');

        $rec1 = VehicleMaintenanceRecord::latest('id')->first();
        $this->assertSame('company', $rec1->payment_responsibility);

        // 2. Driver-owned truck routine maintenance (driver responsibility)
        $this->actingAs($manager)->post(route('fleet.maintenance.store'), [
            'logistics_truck_id' => $driverTruck->id,
            'maintenance_type' => 'routine_maintenance',
            'description' => 'Tire change and alignment',
            'service_provider' => 'Goodyear Servitek',
            'estimated_cost' => 8000.00,
            'actual_cost' => 8000.00,
            'payment_responsibility' => 'driver_owner',
            'responsibility_notes' => 'Routine wear and tear borne by owner-driver per agreement',
            'status' => 'approved',
        ])->assertRedirect()->assertSessionHas('success');

        $rec2 = VehicleMaintenanceRecord::latest('id')->first();
        $this->assertSame('driver_owner', $rec2->payment_responsibility);

        // 3. Accident repair with insurance coverage
        $this->actingAs($manager)->post(route('fleet.maintenance.store'), [
            'logistics_truck_id' => $driverTruck->id,
            'maintenance_type' => 'accident_repair',
            'description' => 'Fender dent and headlight replacement',
            'service_provider' => 'Auto Body Repair Works',
            'estimated_cost' => 12000.00,
            'actual_cost' => 11500.00,
            'payment_responsibility' => 'insurance',
            'responsibility_notes' => 'Insurance claim approved #POL-9921',
            'status' => 'approved',
        ])->assertRedirect()->assertSessionHas('success');

        $rec3 = VehicleMaintenanceRecord::latest('id')->first();
        $this->assertSame('insurance', $rec3->payment_responsibility);
    }

    public function test_damaged_maintenance_and_retired_vehicles_cannot_be_assigned(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $hubA = $this->center($company);
        $hubB = $this->center($company);

        $driverTruck = $this->truck($company, 'available');
        $driver = $this->driver($company, $driverTruck);

        $parcel = $this->parcel($hubA, $hubB);

        // 1. Damaged truck
        $damagedTruck = $this->truck($company, 'damaged');
        $this->assertFalse($damagedTruck->isAssignable());

        $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $damagedTruck->id,
            'driver_id' => $driver->id,
            'parcels' => [$parcel->id],
        ])->assertStatus(422);

        // 2. Maintenance truck
        $maintTruck = $this->truck($company, 'maintenance');
        $this->assertFalse($maintTruck->isAssignable());

        $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $maintTruck->id,
            'driver_id' => $driver->id,
            'parcels' => [$parcel->id],
        ])->assertStatus(422);

        // 3. Retired truck
        $retiredTruck = $this->truck($company, 'retired');
        $this->assertFalse($retiredTruck->isAssignable());

        $this->actingAs($manager)->postJson(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $retiredTruck->id,
            'driver_id' => $driver->id,
            'parcels' => [$parcel->id],
        ])->assertStatus(422);
    }

    public function test_admin_approval_required_before_repaired_vehicle_returns_to_service(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $truck = $this->truck($company, 'available');

        // Put vehicle under maintenance
        $this->actingAs($manager)->post(route('fleet.maintenance.store'), [
            'logistics_truck_id' => $truck->id,
            'maintenance_type' => 'mechanical_repair',
            'description' => 'Brake booster replacement',
            'status' => 'in_repair',
            'payment_responsibility' => 'company',
        ]);

        $truck = $truck->fresh();
        $this->assertSame('maintenance', $truck->status);
        $this->assertFalse($truck->isAssignable());

        $record = VehicleMaintenanceRecord::latest('id')->first();

        // Admin assesses repair, marks completed, and explicitly authorizes return to service
        $resp = $this->actingAs($manager)->put(route('fleet.maintenance.update', $record), [
            'status' => 'completed',
            'actual_cost' => 7500.00,
            'return_to_service' => 1,
            'admin_remarks' => 'Road-tested and passed brake inspection.',
        ]);

        $resp->assertRedirect()->assertSessionHas('success');

        $truck = $truck->fresh();
        $record = $record->fresh();

        $this->assertSame('available', $truck->status);
        $this->assertTrue($truck->isAssignable());
        $this->assertSame('completed', $record->status);
        $this->assertNotNull($record->returned_to_service_at);
        $this->assertSame($manager->id, $record->authorized_by_id);
    }
}
