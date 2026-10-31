<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Manual override controls on the fleet transfer workflow.
 *
 * Runs inside a rolled-back transaction against the disposable sandbox so the
 * shared seed data is never modified.
 */
class HubTransferManualControlsTest extends TestCase
{
    use DatabaseTransactions;

    private int $sequence = 0;

    private function company(string $name): LogisticsCompany
    {
        return LogisticsCompany::create(['name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function center(LogisticsCompany $company, string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name.' '.uniqid(), 'address' => '1 Test Road', 'city' => 'Test City',
            'is_active' => true, 'logistics_company_id' => $company->id,
        ]);
    }

    private function admin(): User
    {
        return User::where('email', 'logistics_admin@test.com')->firstOrFail();
    }

    private function delivery(LogisticsCenter $origin, LogisticsCenter $destination): Delivery
    {
        $this->sequence++;

        return Delivery::create([
            'tracking_number' => 'TRK-MC-'.now()->format('His').'-'.str_pad((string) $this->sequence, 4, '0', STR_PAD_LEFT).'-'.random_int(1000, 9999),
            'sender_name' => 'Sender', 'recipient_name' => 'Recipient',
            'sender_address' => 'Sender Road', 'recipient_address' => 'Destination Road',
            'center_id' => $origin->id, 'destination_center_id' => $destination->id,
            'status' => 'waiting_for_rider', 'parcel_status' => 'sorted', 'weight' => 2,
        ]);
    }

    private function fleet(LogisticsCompany $company): array
    {
        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id, 'plate_number' => 'MC-'.uniqid(),
            'vehicle_type' => 'truck', 'parcel_capacity' => 10, 'capacity_kg' => 500, 'status' => 'available',
        ]);
        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id, 'logistics_truck_id' => $truck->id,
            'name' => 'Transport Driver', 'email' => 'driver-'.uniqid().'@test.local',
            'password' => Hash::make('secret123'), 'status' => 'available', 'is_online' => true,
        ]);

        return [$truck, $driver];
    }

    private function preparedTransfer(): array
    {
        $company = $this->company('Manual Controls');
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $admin = $this->admin();
        [$truck, $driver] = $this->fleet($company);
        $parcel = $this->delivery($origin, $destination);

        $this->actingAs($admin)->post(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id, 'destination_center_id' => $destination->id,
            'truck_id' => $truck->id, 'driver_id' => $driver->id, 'parcels' => [$parcel->id],
        ])->assertSessionHasNoErrors()->assertRedirect();

        return [HubTransfer::latest('id')->firstOrFail(), $company, $origin, $destination, $admin, $truck, $driver, $parcel];
    }

    public function test_loading_marks_transfer_ready_automatically_with_a_system_event(): void
    {
        [$transfer, , , , $admin, , , $parcel] = $this->preparedTransfer();

        $this->actingAs($admin)
            ->post(route('hub-transfers.load', $transfer), ['tracking_number' => $parcel->tracking_number])
            ->assertSessionHasNoErrors();

        $this->assertSame('ready_for_dispatch', $transfer->fresh()->status);
        $automatic = $transfer->fresh()->events()->where('event_type', 'manifest_ready')->firstOrFail();
        $this->assertNull($automatic->actor_id);
        $this->assertSame('ready_for_dispatch', $automatic->new_status);
    }

    public function test_pre_dispatch_cancellation_releases_fleet_and_manifest(): void
    {
        [$transfer, , $origin, , $admin, $truck, $driver, $parcel] = $this->preparedTransfer();

        $this->actingAs($admin)
            ->post(route('hub-transfers.cancel', $transfer), ['reason' => 'Wrong manifest selected'])
            ->assertSessionHasNoErrors();

        $this->assertSame('cancelled', $transfer->fresh()->status);
        $this->assertSame('available', $truck->fresh()->status);
        $this->assertSame('available', $driver->fresh()->status);
        $this->assertSame('removed', $transfer->parcels()->firstOrFail()->loading_status);
        $this->assertSame($origin->id, $parcel->fresh()->center_id);
        $this->assertDatabaseHas('hub_transfer_events', [
            'hub_transfer_id' => $transfer->id,
            'event_type' => 'cancelled',
            'new_status' => 'cancelled',
        ]);

        $this->actingAs($admin)->post(route('hub-transfers.dispatch', $transfer))->assertStatus(409);
    }

    public function test_hold_blocks_movement_until_resumed(): void
    {
        [$transfer, , , , $admin, , , $parcel] = $this->preparedTransfer();
        $this->actingAs($admin)->post(route('hub-transfers.load', $transfer), ['tracking_number' => $parcel->tracking_number]);

        $this->actingAs($admin)
            ->post(route('hub-transfers.hold', $transfer), ['reason' => 'Awaiting clearance'])
            ->assertSessionHasNoErrors();
        $this->assertSame('on_hold', $transfer->fresh()->status);

        $this->actingAs($admin)->post(route('hub-transfers.dispatch', $transfer))->assertStatus(409);

        $this->actingAs($admin)->post(route('hub-transfers.resume', $transfer))->assertSessionHasNoErrors();
        $this->assertSame('ready_for_dispatch', $transfer->fresh()->status);

        $this->actingAs($admin)->post(route('hub-transfers.dispatch', $transfer))->assertSessionHasNoErrors();
        $this->assertSame('in_transit', $transfer->fresh()->status);
    }

    public function test_fleet_can_be_reassigned_only_before_dispatch(): void
    {
        [$transfer, $company, , , $admin, , , $parcel] = $this->preparedTransfer();
        [$replacementTruck, $replacementDriver] = $this->fleet($company);
        $oldTruckId = $transfer->truck_id;

        $this->actingAs($admin)
            ->post(route('hub-transfers.reassign', $transfer), ['truck_id' => $replacementTruck->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($replacementTruck->id, $transfer->fresh()->truck_id);
        $this->assertSame($replacementDriver->id, $transfer->fresh()->driver_id);
        $this->assertSame('available', LogisticsTruck::find($oldTruckId)->status);

        $this->actingAs($admin)->post(route('hub-transfers.load', $transfer), ['tracking_number' => $parcel->tracking_number]);
        $this->actingAs($admin)->post(route('hub-transfers.dispatch', $transfer))->assertSessionHasNoErrors();
        $this->assertSame('in_transit', $transfer->fresh()->status);
        $this->actingAs($admin)
            ->post(route('hub-transfers.reassign', $transfer), ['truck_id' => $oldTruckId])
            ->assertStatus(409);
    }

    public function test_destination_can_be_corrected_before_dispatch_with_audit(): void
    {
        [$transfer, $company, $origin, , $admin, , , $parcel] = $this->preparedTransfer();
        $corrected = $this->center($company, 'Corrected Destination');

        $this->actingAs($admin)
            ->post(route('hub-transfers.reroute', $transfer), [
                'destination_center_id' => $corrected->id,
                'reason' => 'Buyer address is served by a different hub',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($corrected->id, $transfer->fresh()->destination_center_id);
        $this->assertSame($corrected->id, $parcel->fresh()->destination_center_id);
        $this->assertDatabaseHas('hub_transfer_events', [
            'hub_transfer_id' => $transfer->id,
            'event_type' => 'rerouted',
        ]);

        $foreignHub = $this->center($this->company('Other Company'), 'Foreign Hub');
        $this->actingAs($admin)
            ->post(route('hub-transfers.reroute', $transfer), ['destination_center_id' => $foreignHub->id, 'reason' => 'Attempt cross-company'])
            ->assertSessionHasErrors('destination_center_id');

        $this->actingAs($admin)
            ->post(route('hub-transfers.reroute', $transfer), ['destination_center_id' => $origin->id, 'reason' => 'Route back to origin'])
            ->assertStatus(422);
    }

    public function test_missing_parcel_requires_a_reason_and_stays_at_the_origin(): void
    {
        [$transfer, , $origin, , $admin, , , $parcel] = $this->preparedTransfer();
        $this->actingAs($admin)->post(route('hub-transfers.load', $transfer), ['tracking_number' => $parcel->tracking_number]);
        $this->actingAs($admin)->post(route('hub-transfers.dispatch', $transfer));
        $this->actingAs($admin)->post(route('hub-transfers.arrive', $transfer));

        $this->actingAs($admin)
            ->post(route('hub-transfers.receive', $transfer), ['tracking_number' => $parcel->tracking_number, 'condition' => 'missing'])
            ->assertStatus(422);

        $this->actingAs($admin)
            ->post(route('hub-transfers.receive', $transfer), [
                'tracking_number' => $parcel->tracking_number,
                'condition' => 'missing',
                'exception_reason' => 'Manifest parcel not found in the truck',
            ])
            ->assertSessionHasNoErrors();

        $entry = $transfer->fresh()->parcels()->firstOrFail();
        $this->assertSame('missing', $entry->receiving_status);
        $this->assertSame('exception', $transfer->fresh()->status);
        $this->assertSame($origin->id, $parcel->fresh()->center_id);
    }

    public function test_duplicate_receiving_scan_is_rejected(): void
    {
        [$transfer, , , , $admin, , , $parcel] = $this->preparedTransfer();
        $this->actingAs($admin)->post(route('hub-transfers.load', $transfer), ['tracking_number' => $parcel->tracking_number]);
        $this->actingAs($admin)->post(route('hub-transfers.dispatch', $transfer));
        $this->actingAs($admin)->post(route('hub-transfers.arrive', $transfer));

        $this->actingAs($admin)
            ->post(route('hub-transfers.receive', $transfer), ['tracking_number' => $parcel->tracking_number, 'condition' => 'good'])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)
            ->post(route('hub-transfers.receive', $transfer), ['tracking_number' => $parcel->tracking_number, 'condition' => 'good'])
            ->assertSessionHas('error');

        $this->assertSame('received', $transfer->fresh()->parcels()->firstOrFail()->receiving_status);
    }

    public function test_received_transfer_parcel_cannot_be_processed_until_transfer_is_finalized(): void
    {
        [$transfer, , , , $admin, , , $parcel] = $this->preparedTransfer();
        $parcel->update(['tracking_number' => 'TRK-'.now()->format('Ymd').'-ABCD']);

        $this->actingAs($admin)->post(route('hub-transfers.load', $transfer), ['tracking_number' => $parcel->tracking_number]);
        $this->actingAs($admin)->post(route('hub-transfers.dispatch', $transfer));
        $this->actingAs($admin)->post(route('hub-transfers.arrive', $transfer));
        $this->actingAs($admin)->post(route('hub-transfers.receive', $transfer), [
            'tracking_number' => $parcel->tracking_number,
            'condition' => 'good',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin)->get(route('deliveries.show', $parcel))->assertDontSee('Scan & Verify Parcel');
        $this->actingAs($admin)->patch(route('deliveries.scan', $parcel))->assertStatus(409);
        $this->actingAs($admin)->postJson(route('deliveries.scan-verify'), [
            'tracking_number' => $parcel->tracking_number,
        ])->assertStatus(409);
        $this->assertSame('received', $parcel->fresh()->parcel_status);

        $this->actingAs($admin)->post(route('hub-transfers.complete', $transfer))->assertSessionHasNoErrors();
        $this->actingAs($admin)->patch(route('deliveries.scan', $parcel))->assertRedirect();
        $this->assertSame('scanned', $parcel->fresh()->parcel_status);
    }
}
