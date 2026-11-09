<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferEvent;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Destination-hub Incoming visibility plus company vs. hub transfer access.
 *
 * Runs against the isolated MySQL sandbox configured in phpunit.mysql.xml.
 */
class HubTransferIncomingAccessTest extends TestCase
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

    private function manager(LogisticsCompany $company): User
    {
        return User::create([
            'name' => 'Company Manager',
            'email' => 'manager-'.bin2hex(random_bytes(6)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => false,
            'logistics_company_id' => $company->id,
            'center_id' => null,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function staff(LogisticsCompany $company, LogisticsCenter $center): User
    {
        return User::create([
            'name' => 'Hub Staff',
            'email' => 'hub-staff-'.bin2hex(random_bytes(6)).'@test.local',
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

    private function platformAdmin(): User
    {
        return User::create([
            'name' => 'Platform Admin',
            'email' => 'platform-admin-'.bin2hex(random_bytes(6)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function transfer(
        LogisticsCompany $company,
        LogisticsCenter $origin,
        LogisticsCenter $destination,
        string $status = 'in_transit',
    ): HubTransfer {
        return HubTransfer::create([
            'reference' => 'HT-TEST-'.strtoupper(bin2hex(random_bytes(4))),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => $status,
        ]);
    }

    private function delivery(LogisticsCenter $origin, LogisticsCenter $destination, array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'status' => 'assigned',
            'parcel_status' => 'received',
            'tracking_number' => 'TRK-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
            'sender_name' => 'Hub Sender',
            'recipient_name' => 'Hub Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'received_at' => now(),
        ], $extra));
    }

    private function manifest(
        HubTransfer $transfer,
        Delivery $delivery,
        string $loadingStatus = 'loaded',
        string $receivingStatus = 'pending',
    ): HubTransferParcel {
        return HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => $loadingStatus,
            'receiving_status' => $receivingStatus,
        ]);
    }

    /** @return array{0: LogisticsTruck, 1: HubTransportDriver} */
    private function fleet(LogisticsCompany $company): array
    {
        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'PRIVATE-'.strtoupper(bin2hex(random_bytes(4))),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 20,
            'capacity_kg' => 1000,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);
        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck->id,
            'name' => 'Private Origin Driver '.bin2hex(random_bytes(4)),
            'email' => 'private-driver-'.bin2hex(random_bytes(6)).'@test.local',
            'password' => Hash::make('secret123'),
            'status' => 'available',
            'is_online' => true,
        ]);

        return [$truck, $driver];
    }

    public function test_destination_hub_staff_see_only_parcels_incoming_to_their_hub(): void
    {
        $company = $this->company('Incoming Company');
        $origin = $this->center($company, 'Origin Hub');
        $destination = $this->center($company, 'Destination Hub');
        $otherDestination = $this->center($company, 'Other Destination Hub');

        $expectedTransfer = $this->transfer($company, $origin, $destination, 'arrived');
        $expected = $this->delivery($origin, $destination);
        $this->manifest($expectedTransfer, $expected);

        $otherTransfer = $this->transfer($company, $origin, $otherDestination, 'arrived');
        $other = $this->delivery($origin, $otherDestination);
        $this->manifest($otherTransfer, $other);

        $this->actingAs($this->staff($company, $destination))
            ->get(route('sorting-center.index', ['tab' => 'incoming']))
            ->assertOk()
            ->assertSee($expected->tracking_number)
            ->assertSee($expectedTransfer->reference)
            ->assertSee('Verify receipt')
            ->assertDontSee($other->tracking_number)
            ->assertDontSee($otherTransfer->reference);
    }

    public function test_incoming_center_filter_is_keyed_by_the_transfer_destination(): void
    {
        $company = $this->company('Filter Company');
        $origin = $this->center($company, 'Filter Origin');
        $destination = $this->center($company, 'Filter Destination');
        $otherDestination = $this->center($company, 'Filter Other Destination');

        $expectedTransfer = $this->transfer($company, $origin, $destination, 'in_transit');
        $expected = $this->delivery($origin, $destination);
        $this->manifest($expectedTransfer, $expected);

        $otherTransfer = $this->transfer($company, $origin, $otherDestination, 'in_transit');
        $other = $this->delivery($origin, $otherDestination);
        $this->manifest($otherTransfer, $other);

        $this->actingAs($this->platformAdmin())
            ->get(route('sorting-center.index', [
                'tab' => 'incoming',
                'center_id' => $destination->id,
            ]))
            ->assertOk()
            ->assertSee($expected->tracking_number)
            ->assertSee($expectedTransfer->reference)
            ->assertDontSee($other->tracking_number);
    }

    public function test_company_manager_only_sees_transfers_from_their_own_company(): void
    {
        $company = $this->company('Manager Company');
        $origin = $this->center($company, 'Manager Origin');
        $destination = $this->center($company, 'Manager Destination');

        $otherCompany = $this->company('Other Company');
        $otherOrigin = $this->center($otherCompany, 'Other Origin');
        $otherDestination = $this->center($otherCompany, 'Other Destination');

        $ownTransfer = $this->transfer($company, $origin, $destination);
        $foreignTransfer = $this->transfer($otherCompany, $otherOrigin, $otherDestination);

        $manager = $this->manager($company);

        $this->actingAs($manager)
            ->get(route('hub-transfers.index'))
            ->assertOk()
            ->assertSee($ownTransfer->reference)
            ->assertDontSee($foreignTransfer->reference);

        $this->actingAs($manager)
            ->get(route('hub-transfers.show', $ownTransfer))
            ->assertOk()
            ->assertSee($ownTransfer->reference);

        $this->actingAs($manager)
            ->get(route('hub-transfers.show', $foreignTransfer))
            ->assertNotFound();
    }

    public function test_company_manager_can_open_manifests_for_every_hub_in_their_company(): void
    {
        $company = $this->company('Multi Hub Company');
        $first = $this->center($company, 'First Hub');
        $second = $this->center($company, 'Second Hub');
        $third = $this->center($company, 'Third Hub');

        $firstLeg = $this->transfer($company, $first, $second);
        $secondLeg = $this->transfer($company, $second, $third);
        $manager = $this->manager($company);

        $this->actingAs($manager)
            ->get(route('hub-transfers.show', $firstLeg))
            ->assertOk()
            ->assertSee('Parcel manifest');

        $this->actingAs($manager)
            ->get(route('hub-transfers.show', $secondLeg))
            ->assertOk()
            ->assertSee('Parcel manifest');
    }

    public function test_hub_staff_are_limited_to_their_hub_and_global_accounts_can_see_all_companies(): void
    {
        $company = $this->company('Scoped Company');
        $assignedHub = $this->center($company, 'Assigned Hub');
        $otherHub = $this->center($company, 'Other Hub');
        $thirdHub = $this->center($company, 'Third Hub');
        $otherCompany = $this->company('Scoped Other Company');
        $foreignOrigin = $this->center($otherCompany, 'Foreign Origin');
        $foreignDestination = $this->center($otherCompany, 'Foreign Destination');

        $assignedTransfer = $this->transfer($company, $assignedHub, $otherHub);
        $unrelatedTransfer = $this->transfer($company, $otherHub, $thirdHub);
        $foreignTransfer = $this->transfer($otherCompany, $foreignOrigin, $foreignDestination);

        $this->actingAs($this->staff($company, $assignedHub))
            ->get(route('hub-transfers.index'))
            ->assertOk()
            ->assertSee($assignedTransfer->reference)
            ->assertDontSee($unrelatedTransfer->reference)
            ->assertDontSee($foreignTransfer->reference);

        $this->actingAs($this->platformAdmin())
            ->get(route('hub-transfers.index'))
            ->assertOk()
            ->assertSee($assignedTransfer->reference)
            ->assertSee($unrelatedTransfer->reference)
            ->assertSee($foreignTransfer->reference);
    }

    public function test_hub_staff_without_a_valid_center_are_denied_transfer_access(): void
    {
        $company = $this->company('Unassigned Staff Company');
        $origin = $this->center($company, 'Unassigned Origin');
        $destination = $this->center($company, 'Unassigned Destination');
        $transfer = $this->transfer($company, $origin, $destination);
        $staff = User::create([
            'name' => 'Unassigned Hub Staff',
            'email' => 'unassigned-'.bin2hex(random_bytes(6)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'is_admin' => false,
            'logistics_company_id' => $company->id,
            'center_id' => null,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('hub-transfers.index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('hub-transfers.create'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(route('hub-transfers.show', $transfer))
            ->assertForbidden();
    }

    public function test_only_destination_hub_staff_may_receive_manifest_parcels(): void
    {
        $company = $this->company('Receiving Company');
        $origin = $this->center($company, 'Receiving Origin');
        $destination = $this->center($company, 'Receiving Destination');

        $otherCompany = $this->company('Receiving Other Company');
        $otherOrigin = $this->center($otherCompany, 'Receiving Other Origin');
        $otherDestination = $this->center($otherCompany, 'Receiving Other Destination');

        $transfer = $this->transfer($company, $origin, $destination, 'arrived');
        $delivery = $this->delivery($origin, $destination);
        $entry = $this->manifest($transfer, $delivery);

        $payload = [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ];

        $this->actingAs($this->staff($company, $origin))
            ->post(route('hub-transfers.receive', $transfer), $payload)
            ->assertForbidden();

        // An account from a company that touches neither end must not even
        // learn that this manifest exists.
        $this->actingAs($this->staff($otherCompany, $otherDestination))
            ->post(route('hub-transfers.receive', $transfer), $payload)
            ->assertNotFound();

        $this->assertSame('pending', $entry->fresh()->receiving_status);

        $this->actingAs($this->staff($company, $destination))
            ->post(route('hub-transfers.receive', $transfer), [
                'tracking_number' => 'NOT-ON-MANIFEST',
                'condition' => 'good',
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'That tracking number is not on this transfer manifest. Check the parcel before recording its receipt.');

        $this->actingAs($this->staff($company, $destination))
            ->post(route('hub-transfers.receive', $transfer), $payload)
            ->assertRedirect();

        $this->assertSame('received', $entry->fresh()->receiving_status);
        $this->assertEquals($destination->id, $delivery->fresh()->center_id);
        $this->assertSame('received', $delivery->fresh()->parcel_status);
    }

    public function test_company_manager_is_read_only_for_receiving(): void
    {
        $company = $this->company('Manager Receiving Company');
        $origin = $this->center($company, 'Manager Receiving Origin');
        $destination = $this->center($company, 'Manager Receiving Destination');

        $transfer = $this->transfer($company, $origin, $destination, 'arrived');
        $delivery = $this->delivery($origin, $destination);
        $entry = $this->manifest($transfer, $delivery);

        $this->actingAs($this->manager($company))
            ->post(route('hub-transfers.receive', $transfer), [
                'tracking_number' => $delivery->tracking_number,
                'condition' => 'good',
            ])
            ->assertForbidden();

        $this->assertSame('pending', $entry->fresh()->receiving_status);

        // The assigned destination hub staff member performs the receiving action.
        $this->actingAs($this->staff($company, $destination))
            ->post(route('hub-transfers.receive', $transfer), [
                'tracking_number' => $delivery->tracking_number,
                'condition' => 'good',
            ])
            ->assertRedirect();

        $this->assertSame('received', $entry->fresh()->receiving_status);
        $this->assertEquals($destination->id, $delivery->fresh()->center_id);
    }

    public function test_manifest_renders_the_receive_action_only_for_pending_entries(): void
    {
        $company = $this->company('Manifest Company');
        $origin = $this->center($company, 'Manifest Origin');
        $destination = $this->center($company, 'Manifest Destination');

        $transfer = $this->transfer($company, $origin, $destination, 'arrived');
        $pending = $this->delivery($origin, $destination);
        $received = $this->delivery($origin, $destination);
        $missing = $this->delivery($origin, $destination);

        $this->manifest($transfer, $pending, 'loaded', 'pending');
        $this->manifest($transfer, $received, 'loaded', 'received');
        $this->manifest($transfer, $missing, 'loaded', 'missing');

        $response = $this->actingAs($this->staff($company, $destination))
            ->get(route('hub-transfers.show', $transfer))
            ->assertOk()
            ->assertSee($pending->tracking_number)
            ->assertSee($received->tracking_number)
            ->assertSee($missing->tracking_number)
            ->assertDontSee(route('hub-transfers.complete', $transfer), false);

        $receiveUrl = route('hub-transfers.receive', $transfer);

        $this->assertSame(
            1,
            substr_count($response->getContent(), $receiveUrl),
            'Only the pending manifest entry should render a receive form.',
        );
    }

    public function test_receiving_moves_the_parcel_and_missing_entries_keep_origin_custody(): void
    {
        $company = $this->company('Custody Company');
        $origin = $this->center($company, 'Custody Origin');
        $destination = $this->center($company, 'Custody Destination');

        $transfer = $this->transfer($company, $origin, $destination, 'arrived');

        $arriving = $this->delivery($origin, $destination, [
            'parcel_status' => 'scanned',
            'scanned_at' => now(),
            'sorted_at' => now(),
            'dispatched_at' => now(),
        ]);
        $missing = $this->delivery($origin, $destination, [
            'parcel_status' => 'received',
            'received_at' => now()->subDay(),
        ]);

        $arrivingEntry = $this->manifest($transfer, $arriving);
        $missingEntry = $this->manifest($transfer, $missing);

        $staff = $this->staff($company, $destination);

        $this->actingAs($staff)
            ->post(route('hub-transfers.receive', $transfer), [
                'tracking_number' => $arriving->tracking_number,
                'condition' => 'good',
            ])
            ->assertRedirect();

        $arrivingEntry->refresh();
        $arriving->refresh();

        $this->assertSame('received', $arrivingEntry->receiving_status);
        $this->assertEquals($destination->id, $arriving->center_id);
        $this->assertSame('received', $arriving->parcel_status);
        $this->assertNull($arriving->scanned_at);
        $this->assertNull($arriving->sorted_at);
        $this->assertNull($arriving->dispatched_at);

        $this->actingAs($staff)
            ->post(route('hub-transfers.receive', $transfer), [
                'tracking_number' => $missing->tracking_number,
                'condition' => 'missing',
                'exception_reason' => 'Not present in the truck at receiving.',
            ])
            ->assertRedirect();

        $missingEntry->refresh();
        $missing->refresh();

        $this->assertSame('missing', $missingEntry->receiving_status);
        $this->assertNull($missingEntry->received_at);
        $this->assertEquals($origin->id, $missing->center_id);
        $this->assertSame('received', $missing->parcel_status);

        $this->assertSame('exception', $transfer->fresh()->status);
    }

    public function test_duplicate_receiving_is_rejected_without_moving_the_parcel_again(): void
    {
        $company = $this->company('Duplicate Company');
        $origin = $this->center($company, 'Duplicate Origin');
        $destination = $this->center($company, 'Duplicate Destination');

        $transfer = $this->transfer($company, $origin, $destination, 'arrived');
        $delivery = $this->delivery($origin, $destination);
        $entry = $this->manifest($transfer, $delivery);

        $staff = $this->staff($company, $destination);
        $payload = [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ];

        $this->actingAs($staff)
            ->post(route('hub-transfers.receive', $transfer), $payload)
            ->assertRedirect();

        $this->actingAs($staff)
            ->post(route('hub-transfers.receive', $transfer), $payload)
            ->assertRedirect()
            ->assertSessionHas('error', 'This parcel has already been accounted for.');

        $this->assertSame('received', $entry->fresh()->receiving_status);
        $this->assertSame(1, HubTransferParcel::where('hub_transfer_id', $transfer->id)->count());
    }

    public function test_foreign_destination_staff_see_the_manifest_without_origin_fleet_details(): void
    {
        $originCompany = $this->company('Origin Network Co');
        $destinationCompany = $this->company('Destination Network Co');
        $origin = $this->center($originCompany, 'Origin Network Hub');
        $destination = $this->center($destinationCompany, 'Destination Network Hub');

        $transfer = $this->transfer($originCompany, $origin, $destination, 'arrived');
        $delivery = $this->delivery($origin, $destination);
        $entry = $this->manifest($transfer, $delivery);
        [$truck, $driver] = $this->fleet($originCompany);
        $transfer->update(['truck_id' => $truck->id, 'driver_id' => $driver->id]);
        HubTransferEvent::create([
            'hub_transfer_id' => $transfer->id,
            'event_type' => 'truck_assigned',
            'notes' => 'Truck '.$truck->plate_number.' assigned to '.$driver->name.'.',
            'actor_name' => 'Origin Staff Person',
        ]);

        $destinationStaff = $this->staff($destinationCompany, $destination);

        $this->actingAs($destinationStaff)
            ->get(route('hub-transfers.show', $transfer))
            ->assertOk()
            ->assertSee($transfer->reference)
            ->assertSee($delivery->tracking_number)
            ->assertSee(route('hub-transfers.receive', $transfer), false)
            ->assertDontSee($truck->plate_number)
            ->assertDontSee($driver->name)
            ->assertDontSee('Origin Staff Person')
            ->assertDontSee('Reassign Fleet Truck')
            ->assertDontSee('Correct Destination Hub')
            ->assertDontSee('Cancel Transfer');

        // Destination staff still accept custody for their incoming manifest.
        $this->actingAs($destinationStaff)
            ->post(route('hub-transfers.receive', $transfer), [
                'tracking_number' => $delivery->tracking_number,
                'condition' => 'good',
            ])
            ->assertRedirect();

        $this->assertSame('received', $entry->fresh()->receiving_status);
        $this->assertEquals($destination->id, $delivery->fresh()->center_id);
    }

    public function test_destination_staff_cannot_manage_the_origin_lifecycle(): void
    {
        $originCompany = $this->company('Lifecycle Origin Co');
        $destinationCompany = $this->company('Lifecycle Destination Co');
        $origin = $this->center($originCompany, 'Lifecycle Origin Hub');
        $destination = $this->center($destinationCompany, 'Lifecycle Destination Hub');
        $otherOriginHub = $this->center($originCompany, 'Lifecycle Alternate Hub');

        $transfer = $this->transfer($originCompany, $origin, $destination, 'ready_for_dispatch');
        $delivery = $this->delivery($origin, $destination);
        $this->manifest($transfer, $delivery);
        [$truck] = $this->fleet($originCompany);
        $transfer->update(['truck_id' => $truck->id]);

        $destinationStaff = $this->staff($destinationCompany, $destination);
        $originStaff = $this->staff($originCompany, $origin);

        $attempts = [
            ['hub-transfers.dispatch', []],
            ['hub-transfers.cancel', ['reason' => 'Destination should not cancel']],
            ['hub-transfers.reroute', ['destination_center_id' => $otherOriginHub->id, 'reason' => 'Destination should not reroute']],
            ['hub-transfers.reassign', ['truck_id' => $truck->id]],
            ['hub-transfers.hold', ['reason' => 'Destination should not hold']],
        ];

        foreach ($attempts as [$route, $payload]) {
            $this->actingAs($destinationStaff)
                ->post(route($route, $transfer), $payload)
                ->assertForbidden();
        }

        $this->assertSame('ready_for_dispatch', $transfer->fresh()->status);
        $this->assertSame($destination->id, (int) $transfer->fresh()->destination_center_id);

        $this->actingAs($originStaff)
            ->post(route('hub-transfers.hold', $transfer), ['reason' => 'Awaiting dock clearance'])
            ->assertRedirect();
        $this->assertSame('on_hold', $transfer->fresh()->status);

        $this->actingAs($destinationStaff)
            ->post(route('hub-transfers.resume', $transfer))
            ->assertForbidden();
        $this->assertSame('on_hold', $transfer->fresh()->status);

        // Origin staff retain the documented management actions.
        $this->actingAs($originStaff)
            ->post(route('hub-transfers.resume', $transfer))
            ->assertRedirect();
        $this->assertSame('ready_for_dispatch', $transfer->fresh()->status);

        $this->actingAs($originStaff)
            ->post(route('hub-transfers.reroute', $transfer), [
                'destination_center_id' => $otherOriginHub->id,
                'reason' => 'Origin corrected the next hop',
            ])
            ->assertRedirect();
        $this->assertSame($otherOriginHub->id, (int) $transfer->fresh()->destination_center_id);

        $this->actingAs($originStaff)
            ->post(route('hub-transfers.cancel', $transfer), ['reason' => 'Manifest recalled'])
            ->assertRedirect();
        $this->assertSame('cancelled', $transfer->fresh()->status);
    }

    public function test_unrelated_and_off_route_accounts_receive_not_found(): void
    {
        $company = $this->company('Hidden Transfer Co');
        $origin = $this->center($company, 'Hidden Origin');
        $destination = $this->center($company, 'Hidden Destination');
        $thirdHub = $this->center($company, 'Hidden Third Hub');
        $transfer = $this->transfer($company, $origin, $destination);

        $foreignCompany = $this->company('Hidden Foreign Co');
        $foreignOrigin = $this->center($foreignCompany, 'Hidden Foreign Origin');
        $foreignDestination = $this->center($foreignCompany, 'Hidden Foreign Destination');

        $foreignStaff = $this->staff($foreignCompany, $foreignDestination);

        $this->actingAs($foreignStaff)
            ->get(route('hub-transfers.show', $transfer))
            ->assertNotFound();

        $this->actingAs($foreignStaff)
            ->post(route('hub-transfers.hold', $transfer), ['reason' => 'Foreign hold attempt'])
            ->assertNotFound();

        // A same-company account at a third hub is not on either side of the leg.
        $this->actingAs($this->staff($company, $thirdHub))
            ->get(route('hub-transfers.show', $transfer))
            ->assertNotFound();
    }
}
