<?php

namespace Tests\Feature;

use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Dashboard counters and transfer filters for the Hub Transport Driver API.
 *
 * These tests use DatabaseTransactions against the pre-migrated sandbox so no
 * schema is rebuilt and the shared data is rolled back after each test.
 */
class HubCourierDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private function company(): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => 'Dashboard Test Co '.uniqid(),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company): LogisticsCenter
    {
        return LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => 'Hub-'.uniqid(),
            'code' => 'HUB-'.rand(1000, 9999),
            'address' => 'Center Address',
            'city' => 'Metro Manila',
            'status' => 'active',
        ]);
    }

    private function driver(LogisticsCompany $company, array $attributes = []): HubTransportDriver
    {
        return HubTransportDriver::create(array_merge([
            'logistics_company_id' => $company->id,
            'name' => 'Driver-'.uniqid(),
            'email' => 'driver-'.uniqid().'@test.local',
            'phone' => '09'.rand(100000000, 999999999),
            'password' => 'password123',
            'status' => 'available',
            'is_online' => false,
        ], $attributes));
    }

    private function truck(LogisticsCompany $company, ?HubTransportDriver $driver = null): LogisticsTruck
    {
        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TRK-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'ownership_type' => 'company',
            'hub_transport_driver_id' => $driver?->id,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);

        if ($driver) {
            $driver->update(['logistics_truck_id' => $truck->id]);
        }

        return $truck;
    }

    private function transfer(
        LogisticsCompany $company,
        LogisticsCenter $origin,
        LogisticsCenter $destination,
        LogisticsTruck $truck,
        HubTransportDriver $driver,
        string $status,
        ?string $receivedAt = null,
    ): HubTransfer {
        return HubTransfer::create([
            'reference' => 'HT-DASH-'.uniqid(),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => $status,
            'received_at' => $receivedAt,
        ]);
    }

    public function test_completed_counter_counts_received_trips_once_for_the_authenticated_driver(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $destination = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, $driver);
        $otherDriver = $this->driver($company);

        $completed = $this->transfer($company, $origin, $destination, $truck, $driver, 'received', now()->subHour()->toDateTimeString());
        $this->transfer($company, $origin, $destination, $truck, $driver, 'in_transit');
        $this->transfer($company, $origin, $destination, $truck, $otherDriver, 'received', now()->subHour()->toDateTimeString());

        // Historical per-delivery credits must not be mistaken for trips.
        HubTransferEarning::create([
            'logistics_company_id' => $company->id,
            'hub_transfer_id' => null,
            'delivery_id' => null,
            'hub_transport_driver_id' => $driver->id,
            'recipient_role' => HubTransferEarning::ROLE_INTER_HUB_DRIVER,
            'base_amount' => 20,
            'vehicle_allowance' => 0,
            'vehicle_ownership' => 'company',
            'amount' => 20,
            'earned_on' => now()->toDateString(),
        ]);

        Sanctum::actingAs($driver, ['hub-courier']);

        $this->getJson('/api/logistics/courier/dashboard')
            ->assertOk()
            ->assertJsonPath('earnings_summary.completed_trips_count', 1)
            ->assertJsonCount(1, 'completed_transfers')
            ->assertJsonPath('completed_transfers.0.id', $completed->id)
            ->assertJsonPath('courier.is_online', false);
    }

    public function test_completed_trip_is_counted_even_when_it_falls_outside_the_dashboard_display_window(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $destination = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, $driver);

        $completed = $this->transfer($company, $origin, $destination, $truck, $driver, 'received', now()->subDay()->toDateTimeString());
        $completed->forceFill(['created_at' => now()->subDays(2)])->saveQuietly();

        for ($i = 0; $i < 51; $i++) {
            $this->transfer($company, $origin, $destination, $truck, $driver, 'in_transit');
        }

        Sanctum::actingAs($driver, ['hub-courier']);

        $this->getJson('/api/logistics/courier/dashboard')
            ->assertOk()
            ->assertJsonPath('earnings_summary.completed_trips_count', 1)
            ->assertJsonCount(1, 'completed_transfers')
            ->assertJsonPath('completed_transfers.0.id', $completed->id);
    }

    public function test_transfer_filters_share_the_dashboard_lifecycle_and_stay_driver_scoped(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $destination = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, $driver);
        $otherDriver = $this->driver($company);

        $pending = $this->transfer($company, $origin, $destination, $truck, $driver, 'pending');
        $assigned = $this->transfer($company, $origin, $destination, $truck, $driver, 'assigned');
        $ready = $this->transfer($company, $origin, $destination, $truck, $driver, 'ready_for_dispatch');
        $inTransit = $this->transfer($company, $origin, $destination, $truck, $driver, 'in_transit');
        $received = $this->transfer($company, $origin, $destination, $truck, $driver, 'received', now()->toDateTimeString());
        $cancelled = $this->transfer($company, $origin, $destination, $truck, $driver, 'cancelled');
        $foreign = $this->transfer($company, $origin, $destination, $truck, $otherDriver, 'received', now()->toDateTimeString());

        Sanctum::actingAs($driver, ['hub-courier']);

        $idsFor = function (string $query) {
            $response = $this->getJson('/api/logistics/courier/transfers'.$query)->assertOk();

            return collect($response->json('transfers'))->pluck('id')->sort()->values()->all();
        };

        $this->assertSame([$received->id], $idsFor('?filter=completed'));
        $this->assertSame([$ready->id], $idsFor('?filter=ready'));
        $this->assertSame([$inTransit->id], $idsFor('?filter=in_transit'));
        $this->assertSame(
            collect([$pending->id, $assigned->id])->sort()->values()->all(),
            $idsFor('?filter=pending'),
        );
        $this->assertSame(
            collect([$pending->id, $assigned->id, $ready->id, $inTransit->id])->sort()->values()->all(),
            $idsFor('?filter=active'),
        );
        $this->assertSame(
            collect([$pending->id, $assigned->id, $ready->id, $inTransit->id, $received->id, $cancelled->id])->sort()->values()->all(),
            $idsFor('?filter=all'),
        );
        $this->assertSame(
            collect([$pending->id, $assigned->id, $ready->id, $inTransit->id, $received->id, $cancelled->id])->sort()->values()->all(),
            $idsFor(''),
        );

        $this->assertNotContains($foreign->id, $idsFor('?filter=completed'));
        $this->assertNotContains($foreign->id, $idsFor('?filter=all'));

        $this->getJson('/api/logistics/courier/transfers?filter=unknown')->assertStatus(422);
    }

    public function test_status_endpoint_requires_a_valid_vehicle_before_going_online(): void
    {
        $company = $this->company();
        $driver = $this->driver($company);

        Sanctum::actingAs($driver, ['hub-courier']);

        $this->patchJson('/api/logistics/courier/status', ['status' => 'online'])->assertStatus(422);
        $this->assertFalse($driver->fresh()->is_online);

        $this->patchJson('/api/logistics/courier/status', ['status' => 'offline'])
            ->assertOk()
            ->assertJsonPath('status', 'offline');
        $this->assertFalse($driver->fresh()->is_online);

        $this->truck($company, $driver);

        $this->patchJson('/api/logistics/courier/status', ['status' => 'online'])
            ->assertOk()
            ->assertJsonPath('status', 'online')
            ->assertJsonPath('courier.is_online', true);
        $this->assertTrue($driver->fresh()->is_online);

        $this->getJson('/api/logistics/courier/dashboard')
            ->assertOk()
            ->assertJsonPath('courier.is_online', true);
    }

    public function test_rider_tokens_cannot_read_hub_courier_dashboard(): void
    {
        $company = $this->company();
        $driver = $this->driver($company);

        Sanctum::actingAs($driver, ['rider']);

        $this->getJson('/api/logistics/courier/dashboard')->assertStatus(403);
        $this->getJson('/api/logistics/courier/transfers')->assertStatus(403);
    }
}
