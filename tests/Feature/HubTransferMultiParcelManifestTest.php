<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
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
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * A single hub transfer manifest may carry several parcels, and every parcel
 * keeps its own journey while the transfer advances. These tests exercise the
 * complete staff + driver-app workflow end to end.
 */
class HubTransferMultiParcelManifestTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function company(): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => 'Manifest Co '.uniqid().' '.(++$this->seq),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company, string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => $name.' '.uniqid(),
            'code' => 'HUB-'.rand(1000, 9999),
            'address' => '1 Manifest Street',
            'city' => 'Calamba',
            'province' => 'Laguna',
            'is_active' => true,
        ]);
    }

    private function area(LogisticsCenter $center): ServiceArea
    {
        return ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Area '.uniqid(),
            'is_active' => true,
        ]);
    }

    private function manager(LogisticsCompany $company): User
    {
        return User::create([
            'name' => 'Manifest Manager '.uniqid(),
            'email' => 'manifest-manager-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
        ]);
    }

    private function truck(LogisticsCompany $company, int $parcelCapacity = 100, float $capacityKg = 1500.0): LogisticsTruck
    {
        return LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'ownership_type' => 'company',
            'plate_number' => 'MP-'.rand(1000, 9999).'-'.substr(uniqid(), -4),
            'vehicle_type' => 'truck',
            'model' => 'Isuzu Forward',
            'parcel_capacity' => $parcelCapacity,
            'capacity_kg' => $capacityKg,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);
    }

    private function driver(LogisticsCompany $company, LogisticsTruck $truck): HubTransportDriver
    {
        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck->id,
            'name' => 'Manifest Driver '.uniqid(),
            'email' => 'manifest-driver-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'license_number' => 'N01-'.rand(100000, 999999),
            'status' => 'available',
            'is_online' => true,
        ]);

        RiderApplication::create([
            'name' => $driver->name,
            'email' => $driver->email,
            'phone' => '09171234567',
            'vehicle_type' => 'truck',
            'application_type' => 'hub_transport_driver',
            'license_number' => $driver->license_number,
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'status' => 'approved',
            'hub_transport_driver_id' => $driver->id,
            'logistics_truck_id' => $truck->id,
            'reviewed_at' => now(),
            'provisioned_at' => now(),
        ]);

        return $driver;
    }

    /** @return array{0: LogisticsTruck, 1: HubTransportDriver} */
    private function fleet(LogisticsCompany $company, int $parcelCapacity = 100, float $capacityKg = 1500.0): array
    {
        $truck = $this->truck($company, $parcelCapacity, $capacityKg);

        return [$truck, $this->driver($company, $truck)];
    }

    private function delivery(
        LogisticsCenter $origin,
        LogisticsCenter $finalDestination,
        float $weight,
        ?ServiceArea $area = null,
    ): Delivery {
        return Delivery::create([
            'tracking_number' => 'MP-'.rand(100000, 999999).'-'.substr(uniqid(), -4),
            'center_id' => $origin->id,
            'destination_center_id' => $finalDestination->id,
            'service_area_id' => $area?->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'sorted',
            'weight' => $weight,
            'recipient_name' => 'Manifest Customer',
            'recipient_phone' => '0917'.rand(1000000, 9999999),
            'recipient_address' => 'Customer Street',
            'recipient_city' => $finalDestination->city,
        ]);
    }

    private function payload(
        LogisticsCenter $origin,
        LogisticsCenter $nextHop,
        LogisticsTruck $truck,
        HubTransportDriver $driver,
        array $deliveries,
    ): array {
        return [
            'origin_center_id' => $origin->id,
            'destination_center_id' => $nextHop->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => collect($deliveries)->pluck('id')->all(),
        ];
    }

    public function test_three_parcels_share_one_transfer_and_one_fleet_pair(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Calamba Hub');
        $nextHop = $this->center($company, 'Pila Hub');
        $finalDestination = $this->center($company, 'Pila Hub');
        $area = $this->area($finalDestination);
        [$truck, $driver] = $this->fleet($company);

        $deliveries = [
            $this->delivery($origin, $finalDestination, 10.0, $area),
            $this->delivery($origin, $finalDestination, 12.5, $area),
            $this->delivery($origin, $finalDestination, 7.5, $area),
        ];

        // The create page exposes the manifest capacity check and the
        // multi-select checkboxes for all three eligible parcels.
        $this->actingAs($manager)
            ->get(route('hub-transfers.create', [
                'logistics_company_id' => $company->id,
                'origin_center_id' => $origin->id,
            ]))
            ->assertOk()
            ->assertSee('Select a truck to check this manifest')
            ->assertSee('name="parcels[]"', false)
            ->assertSee('data-weighed="1"', false)
            ->assertSee($deliveries[0]->tracking_number)
            ->assertSee($deliveries[2]->tracking_number);

        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->payload($origin, $nextHop, $truck, $driver, $deliveries))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, HubTransfer::where('logistics_company_id', $company->id)->count());
        $transfer = HubTransfer::where('logistics_company_id', $company->id)->firstOrFail();
        $this->assertSame(3, HubTransferParcel::where('hub_transfer_id', $transfer->id)->count());
        $this->assertSame((int) $truck->id, (int) $transfer->truck_id);
        $this->assertSame((int) $driver->id, (int) $transfer->driver_id);
        $this->assertSame('loading', $transfer->status);
        $this->assertSame('assigned', $truck->fresh()->status);
        $this->assertSame('assigned', $driver->fresh()->status);

        // The web manifest shows one transfer, its combined weight, and each
        // parcel's individual journey stage.
        $this->get(route('hub-transfers.show', $transfer))
            ->assertOk()
            ->assertSee('Parcel Journey')
            ->assertSee('Combined weight:')
            ->assertSee('30.00 kg')
            ->assertSee('Added to manifest')
            ->assertSee($deliveries[0]->tracking_number)
            ->assertSee($deliveries[1]->tracking_number)
            ->assertSee($deliveries[2]->tracking_number);

        foreach ($deliveries as $delivery) {
            $fresh = $delivery->fresh();
            $this->assertSame((int) $origin->id, (int) $fresh->center_id);
            $this->assertSame((int) $finalDestination->id, (int) $fresh->destination_center_id);
            $this->assertSame((int) $area->id, (int) $fresh->service_area_id);
            $this->assertSame('sorted', $fresh->parcel_status);
        }

        // The driver app receives exactly one transfer carrying all parcels.
        Sanctum::actingAs($driver, ['hub-courier']);
        $show = $this->getJson('/api/logistics/courier/transfers/'.$transfer->id)->assertOk();
        $show->assertJsonPath('transfer.id', $transfer->id)
            ->assertJsonPath('transfer.parcel_count', 3)
            ->assertJsonPath('transfer.loaded_count', 0)
            ->assertJsonPath('transfer.received_count', 0)
            ->assertJsonPath('transfer.status_label', 'Loading');
        $this->assertSame(30.0, (float) $show->json('transfer.total_weight_kg'));
        $this->assertCount(3, $show->json('transfer.parcels'));

        $labels = collect($show->json('transfer.parcels'))->pluck('journey_label', 'tracking_number');
        foreach ($deliveries as $delivery) {
            $this->assertSame('Added to manifest', $labels[$delivery->tracking_number]);
        }
    }

    public function test_combined_truck_parcel_capacity_is_enforced(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Calamba Hub');
        $destination = $this->center($company, 'Pila Hub');
        [$truck, $driver] = $this->fleet($company, parcelCapacity: 2);

        $deliveries = [
            $this->delivery($origin, $destination, 1.0),
            $this->delivery($origin, $destination, 1.0),
            $this->delivery($origin, $destination, 1.0),
        ];

        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->payload($origin, $destination, $truck, $driver, $deliveries))
            ->assertStatus(422);

        $this->assertSame(0, HubTransfer::where('logistics_company_id', $company->id)->count());
        $this->assertSame(0, HubTransferParcel::whereIn('delivery_id', collect($deliveries)->pluck('id'))->count());
        $this->assertSame('available', $truck->fresh()->status);
        $this->assertSame('available', $driver->fresh()->status);
    }

    public function test_combined_truck_weight_capacity_is_enforced(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Calamba Hub');
        $destination = $this->center($company, 'Pila Hub');
        [$truck, $driver] = $this->fleet($company, parcelCapacity: 100, capacityKg: 25.0);

        $deliveries = [
            $this->delivery($origin, $destination, 10.0),
            $this->delivery($origin, $destination, 10.0),
            $this->delivery($origin, $destination, 10.0),
        ];

        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->payload($origin, $destination, $truck, $driver, $deliveries))
            ->assertStatus(422);

        $this->assertSame(0, HubTransfer::where('logistics_company_id', $company->id)->count());
    }

    public function test_parcels_with_different_final_destinations_cannot_share_a_manifest(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Calamba Hub');
        $nextHop = $this->center($company, 'Pila Hub');
        $otherFinal = $this->center($company, 'Cebu Hub');
        [$truck, $driver] = $this->fleet($company);

        $deliveries = [
            $this->delivery($origin, $nextHop, 2.0),
            $this->delivery($origin, $nextHop, 2.0),
            $this->delivery($origin, $otherFinal, 2.0),
        ];

        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->payload($origin, $nextHop, $truck, $driver, $deliveries))
            ->assertStatus(422);

        $this->assertSame(0, HubTransfer::where('logistics_company_id', $company->id)->count());
    }

    public function test_truck_and_driver_cannot_be_double_booked_into_active_transfers(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Calamba Hub');
        $destination = $this->center($company, 'Pila Hub');
        [$truck, $driver] = $this->fleet($company);

        $first = [
            $this->delivery($origin, $destination, 2.0),
            $this->delivery($origin, $destination, 2.0),
        ];
        $second = [
            $this->delivery($origin, $destination, 2.0),
            $this->delivery($origin, $destination, 2.0),
        ];

        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->payload($origin, $destination, $truck, $driver, $first))
            ->assertSessionHasNoErrors();

        // A different manifest cannot reuse the busy truck/driver pair.
        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->payload($origin, $destination, $truck, $driver, $second))
            ->assertStatus(409);

        // The same parcel cannot join a second active transfer.
        [$freeTruck, $freeDriver] = $this->fleet($company);
        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->payload($origin, $destination, $freeTruck, $freeDriver, [$first[0]]))
            ->assertStatus(422);

        $this->assertSame(1, HubTransfer::where('logistics_company_id', $company->id)->count());
    }

    public function test_transfer_and_parcel_progress_stay_consistent_until_completion(): void
    {
        $company = $this->company();
        $manager = $this->manager($company);
        $origin = $this->center($company, 'Calamba Hub');
        $nextHop = $this->center($company, 'Pila Hub');
        [$truck, $driver] = $this->fleet($company);

        $a = $this->delivery($origin, $nextHop, 4.0);
        $b = $this->delivery($origin, $nextHop, 5.0);
        $c = $this->delivery($origin, $nextHop, 6.0);
        $deliveries = [$a, $b, $c];

        $this->actingAs($manager)
            ->post(route('hub-transfers.store'), $this->payload($origin, $nextHop, $truck, $driver, $deliveries))
            ->assertSessionHasNoErrors();

        $transfer = HubTransfer::where('logistics_company_id', $company->id)->firstOrFail();

        Sanctum::actingAs($driver, ['hub-courier']);
        $this->postJson('/api/logistics/courier/transfers/'.$transfer->id.'/origin-arrival')->assertOk();

        foreach ($deliveries as $delivery) {
            $this->postJson('/api/logistics/courier/transfers/'.$transfer->id.'/load', [
                'tracking_number' => $delivery->tracking_number,
            ])->assertOk();
        }
        $this->postJson('/api/logistics/courier/transfers/'.$transfer->id.'/complete-loading')->assertOk();
        $this->assertSame('ready_for_dispatch', $transfer->fresh()->status);

        $this->getJson('/api/logistics/courier/transfers/'.$transfer->id)
            ->assertOk()
            ->assertJsonPath('transfer.status_label', 'Ready for Dispatch')
            ->assertJsonPath('transfer.loaded_count', 3);
        $this->assertSame(
            ['Loaded / verified', 'Loaded / verified', 'Loaded / verified'],
            collect($this->getJson('/api/logistics/courier/transfers/'.$transfer->id)->json('transfer.parcels'))
                ->pluck('journey_label')->all()
        );

        // Dispatch through the driver app.
        $this->postJson('/api/logistics/courier/transfers/'.$transfer->id.'/dispatch')->assertOk();

        $this->assertSame('in_transit', $transfer->fresh()->status);
        foreach ($deliveries as $delivery) {
            $this->assertSame('dispatched', $delivery->fresh()->parcel_status);
        }
        $this->assertSame(3, DeliveryStatusLog::whereIn('delivery_id', collect($deliveries)->pluck('id'))
            ->where('status', 'hub_transfer_dispatched')->count());

        $dispatched = $this->getJson('/api/logistics/courier/transfers/'.$transfer->id)->assertOk();
        $dispatched->assertJsonPath('transfer.status_label', 'In Transit');
        $this->assertSame(
            ['Dispatched with transfer', 'Dispatched with transfer', 'Dispatched with transfer'],
            collect($dispatched->json('transfer.parcels'))->pluck('journey_label')->all()
        );

        // A repeated dispatch is rejected and must not duplicate journey history.
        $this->postJson('/api/logistics/courier/transfers/'.$transfer->id.'/dispatch')->assertStatus(409);
        $this->assertSame(3, DeliveryStatusLog::whereIn('delivery_id', collect($deliveries)->pluck('id'))
            ->where('status', 'hub_transfer_dispatched')->count());
        $this->assertSame(1, $transfer->fresh()->events()->where('event_type', 'dispatched')->count());

        // Arrival is a transfer milestone only: parcels still await verification.
        $this->postJson('/api/logistics/courier/transfers/'.$transfer->id.'/destination-arrival')->assertOk();
        $arrived = $this->getJson('/api/logistics/courier/transfers/'.$transfer->id)->assertOk();
        $arrived->assertJsonPath('transfer.status_label', 'Arrived')
            ->assertJsonPath('transfer.received_count', 0);
        $this->assertSame(
            ['Arrived with transfer', 'Arrived with transfer', 'Arrived with transfer'],
            collect($arrived->json('transfer.parcels'))->pluck('journey_label')->all()
        );
        foreach ($deliveries as $delivery) {
            $this->assertSame('dispatched', $delivery->fresh()->parcel_status);
        }
        $this->assertSame(0, DeliveryStatusLog::whereIn('delivery_id', collect($deliveries)->pluck('id'))
            ->where('status', 'received')->count());

        // Receiving is per parcel and never advances its siblings.
        $this->actingAs($manager);
        $this->post(route('hub-transfers.receive', $transfer), [
            'tracking_number' => $a->tracking_number,
            'condition' => 'good',
        ])->assertSessionHasNoErrors();

        $this->assertSame('received', $a->fresh()->parcel_status);
        $this->assertSame((int) $nextHop->id, (int) $a->fresh()->center_id);
        $this->assertSame('dispatched', $b->fresh()->parcel_status);
        $this->assertSame('dispatched', $c->fresh()->parcel_status);

        Sanctum::actingAs($driver, ['hub-courier']);
        $partial = $this->getJson('/api/logistics/courier/transfers/'.$transfer->id)->assertOk();
        $partial->assertJsonPath('transfer.status', 'receiving')
            ->assertJsonPath('transfer.status_label', 'Receiving')
            ->assertJsonPath('transfer.received_count', 1);
        $this->assertSame(
            ['Received and verified', 'Arrived with transfer', 'Arrived with transfer'],
            collect($partial->json('transfer.parcels'))->pluck('journey_label')->all()
        );

        $this->actingAs($manager);
        foreach ([$b, $c] as $delivery) {
            $this->post(route('hub-transfers.receive', $transfer), [
                'tracking_number' => $delivery->tracking_number,
                'condition' => 'good',
            ])->assertSessionHasNoErrors();
        }
        $this->post(route('hub-transfers.complete', $transfer))->assertSessionHasNoErrors();

        $this->assertSame('received', $transfer->fresh()->status);
        foreach ($deliveries as $delivery) {
            $this->assertSame('received', $delivery->fresh()->parcel_status);
            $this->assertSame((int) $nextHop->id, (int) $delivery->fresh()->center_id);
        }

        // One trip, one earning row, regardless of the parcel count.
        $this->assertSame(1, HubTransferEarning::where('hub_transfer_id', $transfer->id)->count());
        $this->assertSame(20.0, (float) HubTransferEarning::where('hub_transfer_id', $transfer->id)->sum('amount'));

        Sanctum::actingAs($driver, ['hub-courier']);
        $dashboard = $this->getJson('/api/logistics/courier/dashboard')->assertOk();
        $dashboard->assertJsonPath('earnings_summary.completed_trips_count', 1)
            ->assertJsonCount(1, 'completed_transfers')
            ->assertJsonCount(0, 'available_transfers')
            ->assertJsonPath('completed_transfers.0.parcel_count', 3)
            ->assertJsonPath('completed_transfers.0.status_label', 'Received / Completed')
            ->assertJsonPath('completed_transfers.0.received_count', 3);

        $completed = $this->getJson('/api/logistics/courier/transfers?filter=completed')->assertOk();
        $completed->assertJsonCount(1, 'transfers');
        $this->assertSame(3, $completed->json('transfers.0.parcel_count'));

        $all = $this->getJson('/api/logistics/courier/transfers?filter=all')->assertOk();
        $all->assertJsonCount(1, 'transfers');

        $earnings = $this->getJson('/api/logistics/courier/earnings')->assertOk();
        $earnings->assertJsonCount(1, 'earnings');
        $this->assertSame($transfer->id, $earnings->json('earnings.0.hub_transfer_id'));
        $this->assertSame(20.0, (float) $earnings->json('summary.total_earnings'));
    }
}
