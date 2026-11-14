<?php

namespace Tests\Feature;

use App\Http\Controllers\Logistics\OperationsController;
use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\Rider;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\FulfillmentWorkflow;
use App\Services\HubTransferService;
use App\Support\ParcelWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Unified Operations workspace and parcel-state resolver.
 *
 * Every fixture lives in a self-contained in-memory SQLite schema, so neither
 * the shared MySQL sandbox nor the live database is touched and no migrator
 * runs. These tests describe DB state only; they never post parcel mutations.
 */
class LogisticsOperationsWorkspaceTest extends TestCase
{
    private LogisticsCompany $company;
    private LogisticsCenter $hubA;
    private LogisticsCenter $hubB;
    private User $hubStaff;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->assertSame('sqlite', DB::getDefaultConnection());

        $this->createSchema();
        $this->seedFacilities();
    }

    // ------------------------------------------------------------------
    // Physical arrival never accepts custody by itself
    // ------------------------------------------------------------------

    public function test_arrival_keeps_transfer_custody_until_receiving_is_recorded(): void
    {
        $destination = $this->center($this->company, 'Destination Hub');
        $delivery = $this->delivery([
            'center_id' => $this->hubA->id,
            'destination_center_id' => $destination->id,
            'parcel_status' => 'dispatched',
        ]);
        $transfer = $this->transfer($this->hubA, $destination, 'in_transit');
        $entry = $this->entry($transfer, $delivery, 'loaded', 'pending');

        $this->assertTrue(FulfillmentWorkflow::hasActiveHubTransfer($delivery->fresh()));

        // Truck arrival is a physical milestone; custody stays with the manifest.
        $transfer->update(['status' => 'arrived']);
        $this->assertTrue(FulfillmentWorkflow::hasActiveHubTransfer($delivery->fresh()));

        // Accepting custody releases the parcel even while the manifest is open.
        $entry->update(['receiving_status' => 'received', 'condition' => 'good']);
        $transfer->update(['status' => 'receiving']);
        $this->assertFalse(FulfillmentWorkflow::hasActiveHubTransfer($delivery->fresh()));

        // A missing outcome is an unresolved exception, not an accepted custody.
        $entry->update(['receiving_status' => 'missing', 'condition' => 'missing']);
        $transfer->update(['status' => 'exception']);
        $this->assertTrue(FulfillmentWorkflow::hasActiveHubTransfer($delivery->fresh()));
    }

    // ------------------------------------------------------------------
    // Human-readable labels map to the existing DB states
    // ------------------------------------------------------------------

    public function test_parcel_workflow_maps_db_state_to_labels_and_one_next_step(): void
    {
        $destination = $this->center($this->company, 'Destination Hub');
        $destinationStaff = $this->user('destination.staff@example.test', $destination);

        $parcel = $this->delivery([
            'center_id' => $this->hubA->id,
            'destination_center_id' => $destination->id,
            'parcel_status' => 'dispatched',
        ]);
        $transfer = $this->transfer($this->hubA, $destination, 'in_transit');
        $entry = $this->entry($transfer, $parcel, 'loaded', 'pending');

        $state = ParcelWorkflow::describe($parcel, $entry, $this->hubStaff);
        $this->assertSame('In Transit', $state['label']);
        $this->assertSame('open_transfer', $state['next']['key']);
        $this->assertSame(route('hub-transfers.show', $transfer), $state['next']['url']);

        // Arrived: origin staff can open the manifest but cannot accept custody.
        $transfer->update(['status' => 'arrived']);
        $state = ParcelWorkflow::describe($parcel, $entry->fresh(), $this->hubStaff);
        $this->assertSame('Arrived — Awaiting Receiving', $state['label']);
        $this->assertSame('open_transfer', $state['next']['key']);
        $this->assertFalse($state['next']['enabled']);
        $this->assertStringContainsString('destination hub', $state['next']['reason']);

        // Destination staff get the receiving entry point.
        $state = ParcelWorkflow::describe($parcel, $entry->fresh(), $destinationStaff);
        $this->assertTrue($state['next']['enabled']);
        $this->assertSame('Receive & inspect', $state['next']['label']);
        $this->assertSame(route('hub-transfers.show', $transfer), $state['next']['url']);

        // Custody accepted: the parcel is sortable at the destination hub.
        $entry->update(['receiving_status' => 'received', 'condition' => 'good']);
        $parcel->update(['parcel_status' => 'received', 'center_id' => $destination->id]);
        $state = ParcelWorkflow::describe($parcel->fresh(), $entry->fresh(), $destinationStaff);
        $this->assertSame('Received — Good', $state['label']);
        $this->assertSame('scan', $state['next']['key']);
        $this->assertTrue($state['next']['enabled']);

        // Missing is an exception state with an investigation path, never received.
        $entry->update(['receiving_status' => 'missing', 'condition' => 'missing']);
        $state = ParcelWorkflow::describe($parcel->fresh(), $entry->fresh(), $destinationStaff);
        $this->assertSame('Missing — Investigation Required', $state['label']);
        $this->assertSame('red', $state['tone']);

        // Sorted for this hub -> last-mile assignment.
        $local = $this->delivery([
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubA->id,
            'parcel_status' => 'sorted',
            'status' => 'waiting_for_rider',
        ]);
        $state = ParcelWorkflow::describe($local, null, $this->hubStaff);
        $this->assertSame('Ready for Rider Assignment', $state['label']);
        $this->assertSame('assign_rider', $state['next']['key']);
        $this->assertTrue($state['next']['enabled']);

        // Sorted for another hub -> direct manifest creation with preselection.
        $interHub = $this->delivery([
            'center_id' => $this->hubA->id,
            'destination_center_id' => $destination->id,
            'parcel_status' => 'sorted',
            'status' => 'waiting_for_rider',
        ]);
        $state = ParcelWorkflow::describe($interHub, null, $this->hubStaff);
        $this->assertSame('Preparing Transfer', $state['label']);
        $this->assertSame('create_transfer', $state['next']['key']);
        $this->assertStringContainsString('origin_center_id='.$this->hubA->id, $state['next']['url']);
        $this->assertStringContainsString('parcel='.$interHub->id, $state['next']['url']);

        $delivered = $this->delivery([
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubA->id,
            'status' => 'delivered',
            'parcel_status' => 'dispatched',
            'delivered_at' => now(),
        ]);
        $state = ParcelWorkflow::describe($delivered, null, $this->hubStaff);
        $this->assertSame('Delivered', $state['label']);
        $this->assertSame('open_parcel', $state['next']['key']);
        $this->assertSame('Delivered', $state['next']['label']);
    }

    // ------------------------------------------------------------------
    // Operations queue counts only the authenticated hub's real records
    // ------------------------------------------------------------------

    public function test_operations_counts_are_scoped_to_the_authenticated_hub(): void
    {
        $otherHubSameCompany = $this->center($this->company, 'Second Hub');
        $otherCompany = LogisticsCompany::create(['name' => 'Other Company', 'status' => 'active']);
        $foreignHub = $this->center($otherCompany, 'Foreign Hub');

        $readySorting = $this->delivery(['center_id' => $this->hubA->id, 'parcel_status' => 'received']);
        $readyRider = $this->delivery([
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubA->id,
            'parcel_status' => 'sorted',
            'status' => 'waiting_for_rider',
        ]);

        $preparingTransfer = $this->delivery([
            'center_id' => $this->hubA->id,
            'destination_center_id' => $otherHubSameCompany->id,
            'parcel_status' => 'sorted',
            'status' => 'waiting_for_rider',
        ]);

        $otherHubParcel = $this->delivery(['center_id' => $otherHubSameCompany->id, 'parcel_status' => 'received']);
        $foreignParcel = $this->delivery(['center_id' => $foreignHub->id, 'parcel_status' => 'received']);

        $incoming = $this->transfer($foreignHub, $this->hubA, 'in_transit');
        $exceptionParcel = $this->delivery([
            'center_id' => $foreignHub->id,
            'destination_center_id' => $this->hubA->id,
            'parcel_status' => 'dispatched',
        ]);
        $this->entry($incoming, $exceptionParcel, 'loaded', 'received')
            ->update(['condition' => 'damaged']);

        // A different leg must not leak into this hub's queue.
        $this->transfer($otherHubSameCompany, $otherHubSameCompany, 'in_transit');
        $this->transfer($this->hubA, $otherHubSameCompany, 'loading');

        $view = $this->operationsView($this->hubStaff);
        $data = $view->getData();

        $this->assertSame($this->hubA->id, $data['center']->id);
        $this->assertTrue($data['canProcess']);
        $this->assertSame([
            'action_parcels' => 3,
            'incoming_transfers' => 1,
            'in_transit' => 1,
            'awaiting_receiving' => 0,
            'ready_sorting' => 1,
            'ready_rider' => 1,
            'exceptions' => 1,
        ], $data['counts']);

        $this->assertSame(
            [$readySorting->id, $readyRider->id, $preparingTransfer->id],
            $data['actionParcels']->pluck('delivery.id')->sort()->values()->all(),
        );

        $transferRow = $data['actionParcels']->firstWhere('delivery.id', $preparingTransfer->id);
        $this->assertSame('Preparing Transfer', $transferRow['workflow']['label']);
        $this->assertSame('create_transfer', $transferRow['workflow']['next']['key']);
        $this->assertNotContains($otherHubParcel->id, $data['actionParcels']->pluck('delivery.id')->all());
        $this->assertNotContains($foreignParcel->id, $data['actionParcels']->pluck('delivery.id')->all());
        $this->assertSame($incoming->id, $data['incomingTransfers']->first()->id);
        $this->assertSame(1, $data['outgoingTransfers']->count());

        // Query strings must never widen the facility scope.
        $tampered = $this->operationsView($this->hubStaff, [
            'company_id' => $otherCompany->id,
            'center_id' => $otherHubSameCompany->id,
            'origin_center_id' => $otherHubSameCompany->id,
        ]);
        $this->assertSame($data['counts'], $tampered->getData()['counts']);
        $this->assertSame($this->hubA->id, $tampered->getData()['center']->id);
    }

    public function test_operations_rejects_accounts_without_an_assigned_facility(): void
    {
        $unassigned = User::create([
            'name' => 'Unassigned Staff',
            'first_name' => 'Unassigned',
            'last_name' => 'Staff',
            'email' => 'unassigned.staff@example.test',
            'password' => Hash::make('secret'),
            'role' => 'staff',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $request = Request::create('/logistics/operations', 'GET');
        $request->setUserResolver(fn () => $unassigned);

        try {
            app(OperationsController::class)->index($request);
            $this->fail('An account without an assigned facility must not open the Operations workspace.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_operations_workspace_is_read_only_and_links_to_canonical_actions(): void
    {
        $view = (string) file_get_contents(resource_path('views/logistics/operations/index.blade.php'));

        // The workspace never posts mutations itself; every action links to the
        // page whose controller still performs authorization and validation.
        $this->assertStringNotContainsString('<form', $view);
        $this->assertStringContainsString("route('deliveries.show'", $view);
        $this->assertStringContainsString("route('hub-transfers.show'", $view);
        $this->assertStringContainsString("route('deliveries.scan-page')", $view);
        $this->assertStringContainsString('Read-only monitoring', $view);
    }

    // ------------------------------------------------------------------
    // Cross-company next-hop correction stays consistent with creation
    // ------------------------------------------------------------------

    public function test_cross_company_reroute_is_allowed_before_dispatch_and_audited(): void
    {
        $otherCompany = LogisticsCompany::create(['name' => 'Other Company', 'status' => 'active']);
        $foreignHub = $this->center($otherCompany, 'Foreign Hub');

        $parcel = $this->delivery([
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubB->id,
            'parcel_status' => 'sorted',
            'status' => 'waiting_for_rider',
        ]);
        $transfer = $this->transfer($this->hubA, $this->hubB, 'loading');
        $this->entry($transfer, $parcel, 'loaded', 'pending');

        $rerouted = HubTransferService::reroute($transfer, $foreignHub, $this->hubStaff, 'Shared network next hop');

        $this->assertSame($foreignHub->id, (int) $rerouted->destination_center_id);
        $this->assertSame($foreignHub->id, (int) $parcel->fresh()->destination_center_id);

        $event = $rerouted->events()->where('event_type', 'rerouted')->firstOrFail();
        $this->assertSame($this->hubStaff->id, (int) $event->actor_id);
        $this->assertStringContainsString('Hub #'.$this->hubB->id, $event->notes);
        $this->assertStringContainsString('Hub #'.$foreignHub->id, $event->notes);
        $this->assertStringContainsString('Shared network next hop', $event->notes);

        // The origin can never become the next hop.
        try {
            HubTransferService::reroute($transfer->fresh(), $this->hubA, $this->hubStaff, 'Loop back');
            $this->fail('Rerouting back to the origin hub must be rejected.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        // After dispatch the manifest route is fixed; a new transfer is required.
        $transfer->update(['status' => 'in_transit']);
        try {
            HubTransferService::reroute($transfer->fresh(), $this->hubB, $this->hubStaff, 'Too late');
            $this->fail('A dispatched transfer must not be rerouted.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_operations_search_filters_and_sorting_work_together_within_hub_scope(): void
    {
        $rider = \App\Models\Rider::create([
            'center_id' => $this->hubA->id,
            'name' => 'Marco FastRider',
            'status' => 'active',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now(),
        ]);

        $parcelAlpha = $this->delivery([
            'tracking_number' => 'TRK-20261111-ALPH',
            'center_id' => $this->hubA->id,
            'origin_center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubB->id,
            'sender_name' => 'Elena Sender',
            'recipient_name' => 'Carlos Buyer',
            'parcel_status' => 'scanned',
            'received_at' => now()->subDays(2),
        ]);

        $parcelBravo = $this->delivery([
            'tracking_number' => 'TRK-20261111-BRAV',
            'center_id' => $this->hubA->id,
            'origin_center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubA->id,
            'rider_id' => $rider->id,
            'sender_name' => 'Miguel Merchant',
            'recipient_name' => 'Lucia Recipient',
            'parcel_status' => 'sorted',
            'received_at' => now()->subDay(),
            'sorted_at' => now()->subHours(4),
        ]);

        $parcelDamaged = $this->delivery([
            'tracking_number' => 'TRK-20261111-DMGD',
            'center_id' => $this->hubA->id,
            'origin_center_id' => $this->hubB->id,
            'destination_center_id' => $this->hubA->id,
            'sender_name' => 'Rosa Store',
            'recipient_name' => 'Paolo Client',
            'status' => 'damaged',
            'parcel_status' => 'received',
            'received_at' => now(),
        ]);

        // Foreign hub parcel must never leak through search or filters
        $this->delivery([
            'tracking_number' => 'TRK-20261111-FRGN',
            'center_id' => $this->hubB->id,
            'origin_center_id' => $this->hubB->id,
            'destination_center_id' => $this->hubB->id,
            'sender_name' => 'Elena Sender',
            'recipient_name' => 'Carlos Buyer',
            'parcel_status' => 'scanned',
            'received_at' => now(),
        ]);

        // 1. Search by tracking number
        $byTracking = $this->operationsView($this->hubStaff, ['search' => 'ALPH'])->getData()['actionParcels'];
        $this->assertSame([$parcelAlpha->id], $byTracking->pluck('delivery.id')->all());

        // 2. Search by numeric parcel ID
        $byId = $this->operationsView($this->hubStaff, ['search' => '#'.$parcelBravo->id])->getData()['actionParcels'];
        $this->assertSame([$parcelBravo->id], $byId->pluck('delivery.id')->all());

        // 3. Search by sender and recipient names (never returns hubB parcel)
        $bySender = $this->operationsView($this->hubStaff, ['search' => 'Elena Sender'])->getData()['actionParcels'];
        $this->assertSame([$parcelAlpha->id], $bySender->pluck('delivery.id')->all());

        $byRecipient = $this->operationsView($this->hubStaff, ['search' => 'Lucia Recipient'])->getData()['actionParcels'];
        $this->assertSame([$parcelBravo->id], $byRecipient->pluck('delivery.id')->all());

        // 4. Search by assigned rider
        $byRider = $this->operationsView($this->hubStaff, ['search' => 'Marco FastRider'])->getData()['actionParcels'];
        $this->assertSame([$parcelBravo->id], $byRider->pluck('delivery.id')->all());

        // 5. Filter by parcel_status, hub_id, date_received, condition
        $byStatus = $this->operationsView($this->hubStaff, ['parcel_status' => 'scanned'])->getData()['actionParcels'];
        $this->assertSame([$parcelAlpha->id], $byStatus->pluck('delivery.id')->all());

        $byHub = $this->operationsView($this->hubStaff, ['hub_id' => $this->hubB->id])->getData()['actionParcels'];
        $this->assertEqualsCanonicalizing([$parcelAlpha->id, $parcelDamaged->id], $byHub->pluck('delivery.id')->all());

        $byDate = $this->operationsView($this->hubStaff, [
            'date_received' => now()->subDay()->toDateString(),
        ])->getData()['actionParcels'];
        $this->assertSame([$parcelBravo->id], $byDate->pluck('delivery.id')->all());

        $byConditionDamaged = $this->operationsView($this->hubStaff, ['condition' => 'damaged'])->getData()['actionParcels'];
        $this->assertSame([$parcelDamaged->id], $byConditionDamaged->pluck('delivery.id')->all());
        $this->assertSame('damaged', $byConditionDamaged->first()['condition']);

        $byConditionGood = $this->operationsView($this->hubStaff, ['condition' => 'good'])->getData()['actionParcels'];
        $this->assertEqualsCanonicalizing([$parcelAlpha->id, $parcelBravo->id], $byConditionGood->pluck('delivery.id')->all());

        // 6. Combined search + filter + sort
        $combined = $this->operationsView($this->hubStaff, [
            'condition' => 'good',
            'sort' => 'oldest',
        ])->getData()['actionParcels'];
        $this->assertSame([$parcelAlpha->id, $parcelBravo->id], $combined->pluck('delivery.id')->all());

        $sortedNewest = $this->operationsView($this->hubStaff, [
            'condition' => 'good',
            'sort' => 'newest',
        ])->getData()['actionParcels'];
        $this->assertSame([$parcelBravo->id, $parcelAlpha->id], $sortedNewest->pluck('delivery.id')->all());

        $sortedTrackingDesc = $this->operationsView($this->hubStaff, [
            'sort' => 'tracking_desc',
        ])->getData()['actionParcels'];
        $this->assertSame(
            [$parcelDamaged->id, $parcelBravo->id, $parcelAlpha->id],
            $sortedTrackingDesc->pluck('delivery.id')->all()
        );
    }

    public function test_bulk_receive_validates_every_parcel_prevents_duplicates_and_rejects_damaged_or_unauthorized(): void
    {
        $eligibleParcel = $this->delivery([
            'tracking_number' => 'TRK-20261111-RCV1',
            'center_id' => $this->hubA->id,
            'order_id' => 101,
            'picked_up_at' => now()->subHour(),
            'sorting_center_handoff_at' => now()->subMinutes(30),
            'sorting_center_handoff_rider_id' => 1,
            'parcel_status' => 'pending_arrival',
        ]);

        $unhandedOffOrder = $this->delivery([
            'tracking_number' => 'TRK-20261111-RCV2',
            'center_id' => $this->hubA->id,
            'order_id' => 102,
            'parcel_status' => 'pending_arrival',
        ]);

        $damagedParcel = $this->delivery([
            'tracking_number' => 'TRK-20261111-RCV3',
            'center_id' => $this->hubA->id,
            'status' => 'damaged',
            'parcel_status' => 'pending_arrival',
        ]);

        $foreignParcel = $this->delivery([
            'tracking_number' => 'TRK-20261111-RCV4',
            'center_id' => $this->hubB->id,
            'parcel_status' => 'pending_arrival',
        ]);

        $response = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-receive'), [
            'confirmed' => true,
            'delivery_ids' => [
                $eligibleParcel->id,
                $unhandedOffOrder->id,
                $damagedParcel->id,
                $foreignParcel->id,
            ],
        ]);

        $response->assertStatus(207)
            ->assertJsonPath('succeeded', 1)
            ->assertJsonPath('failed', 3);

        $results = collect($response->json('results'))->keyBy('delivery_id');
        $this->assertSame('succeeded', $results[$eligibleParcel->id]['status']);
        $this->assertSame('failed', $results[$unhandedOffOrder->id]['status']);
        $this->assertStringContainsString('handoff', $results[$unhandedOffOrder->id]['message']);
        $this->assertSame('failed', $results[$damagedParcel->id]['status']);
        $this->assertStringContainsString('individual condition verification', $results[$damagedParcel->id]['message']);
        $this->assertSame('failed', $results[$foreignParcel->id]['status']);

        $this->assertSame('received', $eligibleParcel->fresh()->parcel_status);
        $this->assertNotNull($eligibleParcel->fresh()->received_at);
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $eligibleParcel->id,
            'status' => 'received_at_center',
            'changed_by' => $this->hubStaff->id,
        ]);

        // Duplicate receive attempt on the already-received parcel is rejected
        $duplicate = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-receive'), [
            'confirmed' => true,
            'delivery_ids' => [$eligibleParcel->id],
        ]);
        $duplicate->assertStatus(422)
            ->assertJsonPath('succeeded', 0)
            ->assertJsonPath('failed', 1);
        $this->assertStringContainsString('Duplicate receive blocked', $duplicate->json('results.0.message'));
    }

    public function test_bulk_sort_assigns_eligible_scanned_parcels_and_reports_individual_failures(): void
    {
        $scannedEligible = $this->delivery([
            'tracking_number' => 'TRK-20261111-SRT1',
            'center_id' => $this->hubA->id,
            'parcel_status' => 'scanned',
            'received_at' => now()->subHour(),
            'scanned_at' => now()->subMinutes(20),
        ]);

        $notYetScanned = $this->delivery([
            'tracking_number' => 'TRK-20261111-SRT2',
            'center_id' => $this->hubA->id,
            'parcel_status' => 'received',
            'received_at' => now()->subHour(),
        ]);

        $damagedScanned = $this->delivery([
            'tracking_number' => 'TRK-20261111-SRT3',
            'center_id' => $this->hubA->id,
            'status' => 'damaged',
            'parcel_status' => 'scanned',
            'received_at' => now()->subHour(),
            'scanned_at' => now()->subMinutes(10),
        ]);

        $response = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-sort'), [
            'confirmed' => true,
            'destination_center_id' => $this->hubB->id,
            'delivery_ids' => [
                $scannedEligible->id,
                $notYetScanned->id,
                $damagedScanned->id,
            ],
        ]);

        $response->assertStatus(207)
            ->assertJsonPath('succeeded', 1)
            ->assertJsonPath('failed', 2);

        $results = collect($response->json('results'))->keyBy('delivery_id');
        $this->assertSame('succeeded', $results[$scannedEligible->id]['status']);
        $this->assertSame('failed', $results[$notYetScanned->id]['status']);
        $this->assertStringContainsString('scanned', $results[$notYetScanned->id]['message']);
        $this->assertSame('failed', $results[$damagedScanned->id]['status']);
        $this->assertStringContainsString('individual condition verification', $results[$damagedScanned->id]['message']);

        $fresh = $scannedEligible->fresh();
        $this->assertSame('sorted', $fresh->parcel_status);
        $this->assertSame($this->hubB->id, (int) $fresh->destination_center_id);
        $this->assertNotNull($fresh->sorted_at);
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $scannedEligible->id,
            'status' => 'sorted',
            'changed_by' => $this->hubStaff->id,
        ]);
    }

    public function test_bulk_assign_rider_and_bulk_transfer_validate_every_parcel_and_report_results(): void
    {
        $rider = \App\Models\Rider::create([
            'center_id' => $this->hubA->id,
            'name' => 'Elena LastMile',
            'vehicle_type' => 'motorcycle',
            'vehicle_capacity_kg' => 25,
            'license_plate' => 'ABC-1234',
            'license_expiry' => now()->addYear()->toDateString(),
            'vehicle_verification' => 'verified',
            'status' => 'available',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now(),
        ]);

        $readyLastMile = $this->delivery([
            'tracking_number' => 'TRK-20261111-RDR1',
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubA->id,
            'parcel_status' => 'sorted',
            'status' => 'waiting_for_rider',
            'weight' => 2.5,
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
            'sorted_at' => now()->subMinutes(30),
        ]);

        $readyRemoteTransfer = $this->delivery([
            'tracking_number' => 'TRK-20261111-XFR1',
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubB->id,
            'parcel_status' => 'sorted',
            'status' => 'waiting_for_rider',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
            'sorted_at' => now()->subMinutes(25),
        ]);

        $damagedSorted = $this->delivery([
            'tracking_number' => 'TRK-20261111-DMG4',
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubA->id,
            'parcel_status' => 'sorted',
            'status' => 'damaged',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
            'sorted_at' => now()->subMinutes(20),
        ]);

        // 1. Bulk Assign Rider: assigns readyLastMile, rejects readyRemoteTransfer and damagedSorted
        $assignResp = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-assign-rider'), [
            'confirmed' => true,
            'mode' => 'manual',
            'rider_id' => $rider->id,
            'delivery_ids' => [
                $readyLastMile->id,
                $readyRemoteTransfer->id,
                $damagedSorted->id,
            ],
        ]);

        $assignResp->assertStatus(207)
            ->assertJsonPath('succeeded', 1)
            ->assertJsonPath('failed', 2);

        $assignResults = collect($assignResp->json('results'))->keyBy('delivery_id');
        $this->assertSame('succeeded', $assignResults[$readyLastMile->id]['status']);
        $this->assertSame('failed', $assignResults[$readyRemoteTransfer->id]['status']);
        $this->assertStringContainsString('hub transfer', $assignResults[$readyRemoteTransfer->id]['message']);
        $this->assertSame('failed', $assignResults[$damagedSorted->id]['status']);
        $this->assertStringContainsString('individual condition verification', $assignResults[$damagedSorted->id]['message']);

        $this->assertSame($rider->id, (int) $readyLastMile->fresh()->rider_id);
        $this->assertSame('assigned', $readyLastMile->fresh()->status);

        // 2. Bulk Transfer with destination confirmation: validates readyRemoteTransfer, rejects readyLastMile and damagedSorted
        $transferResp = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-transfer'), [
            'confirmed' => true,
            'destination_center_id' => $this->hubB->id,
            'delivery_ids' => [
                $readyRemoteTransfer->id,
                $readyLastMile->id,
                $damagedSorted->id,
            ],
        ]);

        $transferResp->assertStatus(207)
            ->assertJsonPath('succeeded', 1)
            ->assertJsonPath('failed', 2)
            ->assertJsonPath('valid_parcel_ids', [$readyRemoteTransfer->id]);

        $this->assertStringContainsString('parcels%5B0%5D='.$readyRemoteTransfer->id, (string) $transferResp->json('manifest_url'));
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $readyRemoteTransfer->id,
            'status' => 'transfer_prepared',
            'changed_by' => $this->hubStaff->id,
        ]);
    }

    public function test_operations_quick_actions_stay_in_workspace_and_enforce_permissions_and_single_parcel_flows(): void
    {
        $serviceAreaA = \App\Models\ServiceArea::create([
            'logistics_center_id' => $this->hubA->id,
            'name' => 'Hub A Central Zone',
            'is_active' => true,
        ]);

        $riderUser = $this->user('quick.rider@example.test', $this->hubA);
        $rider = \App\Models\Rider::create([
            'user_id' => $riderUser->id,
            'center_id' => $this->hubA->id,
            'service_area_id' => $serviceAreaA->id,
            'name' => 'Quick Action Rider',
            'email' => 'quick.rider@example.test',
            'vehicle_type' => 'motorcycle',
            'vehicle_capacity_kg' => 25,
            'vehicle_verification' => 'verified',
            'status' => 'active',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now(),
        ]);

        $pendingParcel = $this->delivery([
            'tracking_number' => 'TRK-20261112-QCK1',
            'center_id' => $this->hubA->id,
            'parcel_status' => 'pending_arrival',
            'status' => 'waiting_for_rider',
        ]);
        $receivedParcel = $this->delivery([
            'tracking_number' => 'TRK-20261112-QCK2',
            'center_id' => $this->hubA->id,
            'parcel_status' => 'received',
            'received_at' => now()->subHour(),
            'status' => 'waiting_for_rider',
        ]);
        $scannedParcel = $this->delivery([
            'tracking_number' => 'TRK-20261112-QCK3',
            'center_id' => $this->hubA->id,
            'parcel_status' => 'scanned',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subMinutes(40),
            'status' => 'waiting_for_rider',
        ]);
        $sortedLocalParcel = $this->delivery([
            'tracking_number' => 'TRK-20261112-QCK4',
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubA->id,
            'service_area_id' => $serviceAreaA->id,
            'parcel_status' => 'sorted',
            'received_at' => now()->subHours(3),
            'scanned_at' => now()->subHours(2),
            'sorted_at' => now()->subMinutes(30),
            'status' => 'waiting_for_rider',
        ]);
        $sortedTransferParcel = $this->delivery([
            'tracking_number' => 'TRK-20261112-QCK5',
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubB->id,
            'parcel_status' => 'sorted',
            'received_at' => now()->subHours(3),
            'scanned_at' => now()->subHours(2),
            'sorted_at' => now()->subMinutes(20),
            'status' => 'waiting_for_rider',
        ]);

        // Verify Operations index renders in-page quick action buttons instead of redirecting to deliveries.show
        $page = $this->actingAs($this->hubStaff)->get(route('operations.index'));
        $page->assertOk();
        $html = $page->getContent();

        $this->assertStringContainsString("openQuickAction({$pendingParcel->id}, 'receive')", $html);
        $this->assertStringContainsString('quickScanParcel(', $html);
        $this->assertStringContainsString("openQuickAction({$scannedParcel->id}, 'sort')", $html);
        $this->assertStringContainsString("openQuickAction({$sortedLocalParcel->id}, 'assign_rider')", $html);
        $this->assertStringContainsString("openQuickAction({$sortedTransferParcel->id}, 'transfer')", $html);
        $this->assertStringNotContainsString(
            '<a href="'.route('deliveries.show', $scannedParcel).'" class="btn btn-primary btn-xs whitespace-nowrap">Sort parcel</a>',
            $html
        );
        // Detailed parcel view link on tracking number is still preserved
        $this->assertStringContainsString(route('deliveries.show', $scannedParcel), $html);

        // Execute full single-parcel quick action flow on $pendingParcel directly through Operations routes
        $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-receive'), [
            'confirmed' => true,
            'delivery_ids' => [$pendingParcel->id],
        ])->assertOk()->assertJsonPath('succeeded', 1);
        $this->assertSame('received', $pendingParcel->fresh()->parcel_status);

        $lookup = $this->actingAs($this->hubStaff)->postJson(route('deliveries.scan-lookup'), [
            'tracking_number' => $pendingParcel->tracking_number,
        ])->assertOk()->assertJsonPath('can_confirm', true);
        $this->assertSame($pendingParcel->id, $lookup->json('delivery.id'));

        $verify = $this->actingAs($this->hubStaff)->postJson(route('deliveries.scan-verify'), [
            'tracking_number' => $pendingParcel->tracking_number,
        ])->assertOk()->assertJsonPath('delivery.parcel_status', 'scanned');
        $this->assertSame('sort', $verify->json('delivery.next_action.key'));

        $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-sort'), [
            'confirmed' => true,
            'destination_center_id' => $this->hubA->id,
            'service_area_id' => $serviceAreaA->id,
            'delivery_ids' => [$pendingParcel->id],
        ])->assertOk()->assertJsonPath('succeeded', 1);
        $this->assertSame('sorted', $pendingParcel->fresh()->parcel_status);

        $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-assign-rider'), [
            'confirmed' => true,
            'mode' => 'manual',
            'rider_id' => $rider->id,
            'delivery_ids' => [$pendingParcel->id],
        ])->assertOk()->assertJsonPath('succeeded', 1);
        $this->assertSame('assigned', $pendingParcel->fresh()->status);

        // Unauthorized management user without assigned center is forbidden from counter actions
        $manager = User::create([
            'name' => 'Company Manager',
            'first_name' => 'Company',
            'last_name' => 'Manager',
            'email' => 'ops.manager@example.test',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $this->company->id,
            'center_id' => null,
        ]);

        $this->actingAs($manager)->postJson(route('operations.bulk-receive'), [
            'confirmed' => true,
            'delivery_ids' => [$receivedParcel->id],
        ])->assertForbidden();

        $this->actingAs($manager)->postJson(route('operations.bulk-sort'), [
            'confirmed' => true,
            'destination_center_id' => $this->hubA->id,
            'service_area_id' => $serviceAreaA->id,
            'delivery_ids' => [$scannedParcel->id],
        ])->assertForbidden();

        $this->actingAs($manager)->postJson(route('operations.bulk-transfer'), [
            'confirmed' => true,
            'destination_center_id' => $this->hubB->id,
            'delivery_ids' => [$sortedTransferParcel->id],
        ])->assertForbidden();
    }

    public function test_bulk_eligibility_counters_accurately_reflect_selected_and_mixed_parcels_including_order_linked_scanned_parcels(): void
    {
        // Replicate the exact 5-parcel Manila queue scenario:
        // - 2 Scanned — Ready for Sorting (Good) order-linked parcels already received/scanned at hub
        // - 3 Received — Good order-linked parcels already received at hub (awaiting scan)
        $scanned1 = $this->delivery([
            'tracking_number' => 'TRK-20260819-0011',
            'order_id' => 6,
            'center_id' => $this->hubA->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'scanned',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
            'picked_up_at' => null,
            'sorting_center_handoff_at' => null,
            'sorting_center_handoff_rider_id' => null,
        ]);
        $scanned2 = $this->delivery([
            'tracking_number' => 'TRK-20260819-0006',
            'order_id' => 18,
            'center_id' => $this->hubA->id,
            'status' => 'arrived_at_shop',
            'parcel_status' => 'scanned',
            'received_at' => now()->subHours(3),
            'scanned_at' => now()->subMinutes(45),
            'picked_up_at' => null,
            'sorting_center_handoff_at' => null,
            'sorting_center_handoff_rider_id' => null,
        ]);
        $received1 = $this->delivery([
            'tracking_number' => 'TRK-20260819-0004',
            'order_id' => 5,
            'center_id' => $this->hubA->id,
            'status' => 'accepted',
            'parcel_status' => 'received',
            'received_at' => now()->subHours(4),
        ]);
        $received2 = $this->delivery([
            'tracking_number' => 'TRK-20260819-0005',
            'order_id' => 10,
            'center_id' => $this->hubA->id,
            'status' => 'going_to_pickup',
            'parcel_status' => 'received',
            'received_at' => now()->subHours(4),
        ]);
        $received3 = $this->delivery([
            'tracking_number' => 'TRK-20260819-0003',
            'order_id' => 4,
            'center_id' => $this->hubA->id,
            'status' => 'assigned',
            'parcel_status' => 'received',
            'received_at' => now()->subHours(5),
        ]);

        $viewData = $this->operationsView($this->hubStaff)->getData();
        $byId = collect($viewData['actionParcels'])->keyBy(fn ($item) => $item['delivery']->id);

        $this->assertCount(5, $byId);

        // Both scanned parcels must be eligible for Bulk Assign to Sorting Batch (and nothing else)
        foreach ([$scanned1->id, $scanned2->id] as $scannedId) {
            $this->assertSame([
                'receive' => false,
                'sort' => true,
                'assign_rider' => false,
                'transfer' => false,
            ], $byId[$scannedId]['eligibility']);
        }

        // All 3 received parcels are already received and not yet scanned -> 0 across all 4 bulk actions
        foreach ([$received1->id, $received2->id, $received3->id] as $receivedId) {
            $this->assertSame([
                'receive' => false,
                'sort' => false,
                'assign_rider' => false,
                'transfer' => false,
            ], $byId[$receivedId]['eligibility']);
        }

        // Simulate Select-All (all 5 selected):
        $allSelectedIds = [$scanned1->id, $scanned2->id, $received1->id, $received2->id, $received3->id];
        $countFor = fn (array $selected, string $action): int => $byId
            ->only($selected)
            ->filter(fn ($item) => ! empty($item['eligibility'][$action]))
            ->count();

        $this->assertSame(0, $countFor($allSelectedIds, 'receive'));
        $this->assertSame(2, $countFor($allSelectedIds, 'sort'));
        $this->assertSame(0, $countFor($allSelectedIds, 'transfer'));
        $this->assertSame(0, $countFor($allSelectedIds, 'assign_rider'));

        // Simulate individual selection (1 scanned parcel + 1 received parcel):
        $partialSelection = [$scanned1->id, $received1->id];
        $this->assertSame(0, $countFor($partialSelection, 'receive'));
        $this->assertSame(1, $countFor($partialSelection, 'sort'));
        $this->assertSame(0, $countFor($partialSelection, 'transfer'));
        $this->assertSame(0, $countFor($partialSelection, 'assign_rider'));

        // Verify the rendered data-parcels attribute on #operations-workspace-root matches
        $pageResponse = $this->actingAs($this->hubStaff)->get(route('operations.index'));
        $pageResponse->assertOk();
        $html = $pageResponse->getContent();
        $this->assertMatchesRegularExpression("/data-parcels='([^']+)'/", $html);
        preg_match("/data-parcels='([^']+)'/", $html, $matches);
        $frontendParcels = collect(json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR))->keyBy('id');

        $this->assertTrue($frontendParcels[$scanned1->id]['eligibility']['sort']);
        $this->assertTrue($frontendParcels[$scanned2->id]['eligibility']['sort']);
        $this->assertFalse($frontendParcels[$received1->id]['eligibility']['sort']);
        $this->assertFalse($frontendParcels[$received2->id]['eligibility']['sort']);
        $this->assertFalse($frontendParcels[$received3->id]['eligibility']['sort']);

        // Submitting all 5 selected parcels to operations.bulk-sort processes the exact 2 eligible scanned parcels
        $sortResp = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-sort'), [
            'confirmed' => true,
            'destination_center_id' => $this->hubB->id,
            'delivery_ids' => $allSelectedIds,
        ]);

        $sortResp->assertStatus(207)
            ->assertJsonPath('succeeded', 2)
            ->assertJsonPath('failed', 3);

        $this->assertSame('sorted', $scanned1->fresh()->parcel_status);
        $this->assertSame('sorted', $scanned2->fresh()->parcel_status);
        $this->assertSame('received', $received1->fresh()->parcel_status);
    }

    public function test_bulk_sorting_enforces_current_hub_custody_and_never_overwrites_conflicting_destinations(): void
    {
        $pilaHub = $this->hubA;
        $staCruzHub = $this->hubB;
        $staRosaHub = $this->center($this->company, 'Sta. Rosa Hub');

        $staCruzArea = ServiceArea::create([
            'logistics_center_id' => $staCruzHub->id,
            'name' => 'Sta. Cruz Poblacion',
            'is_active' => true,
        ]);
        $staRosaArea = ServiceArea::create([
            'logistics_center_id' => $staRosaHub->id,
            'name' => 'Sta. Rosa Balibago',
            'is_active' => true,
        ]);

        // Parcel 1 & 2: At Pila Hub, scanned, no pre-assigned destination yet
        $p1 = $this->delivery([
            'tracking_number' => 'TRK-20261112-1001',
            'center_id' => $pilaHub->id,
            'origin_center_id' => $pilaHub->id,
            'destination_center_id' => null,
            'parcel_status' => 'scanned',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
        ]);
        $p2 = $this->delivery([
            'tracking_number' => 'TRK-20261112-1002',
            'center_id' => $pilaHub->id,
            'origin_center_id' => $pilaHub->id,
            'destination_center_id' => $staCruzHub->id,
            'service_area_id' => $staCruzArea->id,
            'parcel_status' => 'scanned',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
        ]);

        // Parcel 3: Pre-assigned to Sta. Rosa Hub (conflicts with Sta. Cruz batch)
        $p3 = $this->delivery([
            'tracking_number' => 'TRK-20261112-1003',
            'center_id' => $pilaHub->id,
            'origin_center_id' => $pilaHub->id,
            'destination_center_id' => $staRosaHub->id,
            'service_area_id' => $staRosaArea->id,
            'parcel_status' => 'scanned',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
        ]);

        // Parcel 4: Physically in custody at Sta. Cruz Hub (different current hub)
        $p4AtDifferentHub = $this->delivery([
            'tracking_number' => 'TRK-20261112-1004',
            'center_id' => $staCruzHub->id,
            'origin_center_id' => $staCruzHub->id,
            'destination_center_id' => null,
            'parcel_status' => 'scanned',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
        ]);

        // Parcel 5: Attached to an active hub transfer (must be blocked from sorting)
        $p5ActiveTransfer = $this->delivery([
            'tracking_number' => 'TRK-20261112-1005',
            'center_id' => $pilaHub->id,
            'origin_center_id' => $pilaHub->id,
            'parcel_status' => 'scanned',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
        ]);
        $activeTransfer = $this->transfer($pilaHub, $staCruzHub, 'pending');
        $this->entry($activeTransfer, $p5ActiveTransfer, 'loaded', 'pending');

        // 1. Attempting to pass an unrelated current_center_id as the sorting location is rejected (422)
        $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-sort'), [
            'confirmed' => true,
            'current_center_id' => $staRosaHub->id,
            'destination_center_id' => $staCruzHub->id,
            'service_area_id' => $staCruzArea->id,
            'delivery_ids' => [$p1->id],
        ])->assertStatus(422);

        // 2. Attempting to sort with a service_area_id that belongs to a different hub is rejected (422)
        $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-sort'), [
            'confirmed' => true,
            'current_center_id' => $pilaHub->id,
            'destination_center_id' => $staCruzHub->id,
            'service_area_id' => $staRosaArea->id,
            'delivery_ids' => [$p1->id],
        ])->assertStatus(422);

        // 3. Bulk sort for Sta. Cruz Hub with all 5 parcels selected:
        //    - $p1 (unassigned dest at Pila Hub) -> succeeds
        //    - $p2 (already pre-assigned to Sta. Cruz Hub at Pila Hub) -> succeeds
        //    - $p3 (pre-assigned to Sta. Rosa Hub) -> REJECTED so its Sta. Rosa destination is NOT overwritten!
        //    - $p4AtDifferentHub (physically at Sta. Cruz Hub, not Pila Hub) -> REJECTED
        //    - $p5ActiveTransfer (on active transfer) -> REJECTED
        $resp = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-sort'), [
            'confirmed' => true,
            'current_center_id' => $pilaHub->id,
            'destination_center_id' => $staCruzHub->id,
            'service_area_id' => $staCruzArea->id,
            'delivery_ids' => [$p1->id, $p2->id, $p3->id, $p4AtDifferentHub->id, $p5ActiveTransfer->id],
        ]);

        $resp->assertStatus(207)
            ->assertJsonPath('succeeded', 2)
            ->assertJsonPath('failed', 3)
            ->assertJsonPath('current_center_id', $pilaHub->id)
            ->assertJsonPath('destination_center_id', $staCruzHub->id)
            ->assertJsonPath('sorting_action', 'inter_hub_transfer');

        // Verify sorting never changes current hub (center_id) on sorted parcels
        $this->assertSame($pilaHub->id, (int) $p1->fresh()->center_id);
        $this->assertSame($staCruzHub->id, (int) $p1->fresh()->destination_center_id);
        $this->assertSame($staCruzArea->id, (int) $p1->fresh()->service_area_id);
        $this->assertSame('sorted', $p1->fresh()->parcel_status);

        $this->assertSame($pilaHub->id, (int) $p2->fresh()->center_id);
        $this->assertSame($staCruzHub->id, (int) $p2->fresh()->destination_center_id);
        $this->assertSame('sorted', $p2->fresh()->parcel_status);

        // Verify $p3 kept its Sta. Rosa Hub destination and was NOT overwritten with Sta. Cruz Hub!
        $this->assertSame($staRosaHub->id, (int) $p3->fresh()->destination_center_id);
        $this->assertSame($staRosaArea->id, (int) $p3->fresh()->service_area_id);
        $this->assertSame('scanned', $p3->fresh()->parcel_status);

        // Verify $p4AtDifferentHub and $p5ActiveTransfer remained untouched
        $this->assertSame($staCruzHub->id, (int) $p4AtDifferentHub->fresh()->center_id);
        $this->assertSame('scanned', $p4AtDifferentHub->fresh()->parcel_status);
        $this->assertSame('scanned', $p5ActiveTransfer->fresh()->parcel_status);
    }

    public function test_mixed_destination_bulk_hub_transfer_groups_by_actual_destination_and_never_combines_or_overwrites_destinations(): void
    {
        $pilaHub = $this->hubA;
        $staCruzHub = $this->hubB;
        $staRosaHub = $this->center($this->company, 'Sta. Rosa Hub');

        // 3 sorted parcels at Pila Hub:
        // Parcel A -> Sta. Cruz Hub
        // Parcel B -> Sta. Rosa Hub
        // Parcel C -> Sta. Cruz Hub
        $parcelA = $this->delivery([
            'tracking_number' => 'TRK-20261112-2001',
            'center_id' => $pilaHub->id,
            'origin_center_id' => $pilaHub->id,
            'destination_center_id' => $staCruzHub->id,
            'parcel_status' => 'sorted',
            'received_at' => now()->subHours(3),
            'scanned_at' => now()->subHours(2),
            'sorted_at' => now()->subHour(),
        ]);
        $parcelB = $this->delivery([
            'tracking_number' => 'TRK-20261112-2002',
            'center_id' => $pilaHub->id,
            'origin_center_id' => $pilaHub->id,
            'destination_center_id' => $staRosaHub->id,
            'parcel_status' => 'sorted',
            'received_at' => now()->subHours(3),
            'scanned_at' => now()->subHours(2),
            'sorted_at' => now()->subHour(),
        ]);
        $parcelC = $this->delivery([
            'tracking_number' => 'TRK-20261112-2003',
            'center_id' => $pilaHub->id,
            'origin_center_id' => $pilaHub->id,
            'destination_center_id' => $staCruzHub->id,
            'parcel_status' => 'sorted',
            'received_at' => now()->subHours(3),
            'scanned_at' => now()->subHours(2),
            'sorted_at' => now()->subHour(),
        ]);

        // 1. Submitting all 3 parcels without forcing a single destination_center_id:
        //    Backend blocks combined single-manifest creation (422) and returns the 2 separate destination_groups
        $mixedResp = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-transfer'), [
            'confirmed' => true,
            'delivery_ids' => [$parcelA->id, $parcelB->id, $parcelC->id],
        ]);

        $mixedResp->assertStatus(422)
            ->assertJsonPath('manifest_url', null);

        $groups = collect($mixedResp->json('destination_groups'))->keyBy('destination_center_id');
        $this->assertCount(2, $groups);

        // Group 1: Sta. Cruz Hub — Parcels A & C
        $this->assertSame(2, $groups[$staCruzHub->id]['parcel_count']);
        $this->assertEqualsCanonicalizing([$parcelA->id, $parcelC->id], $groups[$staCruzHub->id]['parcel_ids']);
        $this->assertNotEmpty($groups[$staCruzHub->id]['manifest_url']);
        $this->assertStringContainsString('destination_center_id='.$staCruzHub->id, $groups[$staCruzHub->id]['manifest_url']);

        // Group 2: Sta. Rosa Hub — Parcel B
        $this->assertSame(1, $groups[$staRosaHub->id]['parcel_count']);
        $this->assertSame([$parcelB->id], $groups[$staRosaHub->id]['parcel_ids']);
        $this->assertNotEmpty($groups[$staRosaHub->id]['manifest_url']);
        $this->assertStringContainsString('destination_center_id='.$staRosaHub->id, $groups[$staRosaHub->id]['manifest_url']);

        // 2. Submitting all 3 parcels with Sta. Cruz Hub selected as destination_center_id:
        //    Parcels A & C succeed; Parcel B (Sta. Rosa Hub) is rejected; Parcel B's destination is NEVER overwritten!
        $partialResp = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-transfer'), [
            'confirmed' => true,
            'destination_center_id' => $staCruzHub->id,
            'delivery_ids' => [$parcelA->id, $parcelB->id, $parcelC->id],
        ]);

        $partialResp->assertStatus(207)
            ->assertJsonPath('succeeded', 2)
            ->assertJsonPath('failed', 1)
            ->assertJsonPath('valid_parcel_ids', [$parcelA->id, $parcelC->id]);

        // Verify no parcel had its destination_center_id changed
        $this->assertSame($staCruzHub->id, (int) $parcelA->fresh()->destination_center_id);
        $this->assertSame($staRosaHub->id, (int) $parcelB->fresh()->destination_center_id);
        $this->assertSame($staCruzHub->id, (int) $parcelC->fresh()->destination_center_id);
    }

    public function test_bulk_rider_assignment_enforces_service_area_company_approval_availability_and_blocks_incompatible_assignments(): void
    {
        $areaNorth = ServiceArea::create([
            'logistics_center_id' => $this->hubA->id,
            'name' => 'Area North',
            'is_active' => true,
        ]);
        $areaSouth = ServiceArea::create([
            'logistics_center_id' => $this->hubA->id,
            'name' => 'Area South',
            'is_active' => true,
        ]);

        $parcelNorth = $this->delivery([
            'tracking_number' => 'TRK-20261112-3001',
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubA->id,
            'service_area_id' => $areaNorth->id,
            'parcel_status' => 'sorted',
            'status' => 'waiting_for_rider',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
            'sorted_at' => now()->subMinutes(30),
        ]);
        $parcelSouth = $this->delivery([
            'tracking_number' => 'TRK-20261112-3002',
            'center_id' => $this->hubA->id,
            'destination_center_id' => $this->hubA->id,
            'service_area_id' => $areaSouth->id,
            'parcel_status' => 'sorted',
            'status' => 'waiting_for_rider',
            'received_at' => now()->subHours(2),
            'scanned_at' => now()->subHour(),
            'sorted_at' => now()->subMinutes(30),
        ]);

        // Rider 1: Approved, online, two-wheel, authorized ONLY for Area North
        $riderNorth = Rider::create([
            'center_id' => $this->hubA->id,
            'service_area_id' => $areaNorth->id,
            'name' => 'Rider North',
            'vehicle_type' => 'Motorcycle',
            'vehicle_verification' => 'verified',
            'status' => 'active',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now()->subDay(),
            'license_expiry' => now()->addYear()->toDateString(),
        ]);

        // Rider 2: Unapproved / offline rider in Area South
        $riderUnapproved = Rider::create([
            'center_id' => $this->hubA->id,
            'service_area_id' => $areaSouth->id,
            'name' => 'Unapproved Rider',
            'vehicle_type' => 'Motorcycle',
            'vehicle_verification' => 'pending',
            'status' => 'pending',
            'is_online' => false,
            'is_verified' => false,
            'approved_at' => null,
        ]);

        // Rider 3: Rider from another logistics company
        $otherCompany = $this->company('Rival Logistics');
        $otherHub = $this->center($otherCompany, 'Rival Hub');
        $riderOtherCompany = Rider::create([
            'center_id' => $otherHub->id,
            'name' => 'Rival Rider',
            'vehicle_type' => 'Motorcycle',
            'vehicle_verification' => 'verified',
            'status' => 'active',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now()->subDay(),
            'license_expiry' => now()->addYear()->toDateString(),
        ]);

        // 1. Rider from another company is rejected (422)
        $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-assign-rider'), [
            'confirmed' => true,
            'mode' => 'manual',
            'rider_id' => $riderOtherCompany->id,
            'delivery_ids' => [$parcelNorth->id],
        ])->assertStatus(422);

        // 2. Unapproved / offline rider is rejected (422)
        $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-assign-rider'), [
            'confirmed' => true,
            'mode' => 'manual',
            'rider_id' => $riderUnapproved->id,
            'delivery_ids' => [$parcelSouth->id],
        ])->assertStatus(422);

        // 3. Assigning both Area North and Area South parcels to Rider North (authorized ONLY for Area North):
        //    - $parcelNorth succeeds
        //    - $parcelSouth is rejected because Rider North is not authorized for Area South!
        $mixedAssign = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-assign-rider'), [
            'confirmed' => true,
            'mode' => 'manual',
            'rider_id' => $riderNorth->id,
            'delivery_ids' => [$parcelNorth->id, $parcelSouth->id],
        ]);

        $mixedAssign->assertStatus(207)
            ->assertJsonPath('succeeded', 1)
            ->assertJsonPath('failed', 1);

        $this->assertCount(2, $mixedAssign->json('service_area_groups'));
        $this->assertSame($riderNorth->id, (int) $parcelNorth->fresh()->rider_id);
        $this->assertSame('assigned', $parcelNorth->fresh()->status);

        // $parcelSouth must remain unassigned
        $this->assertNull($parcelSouth->fresh()->rider_id);
        $this->assertSame('waiting_for_rider', $parcelSouth->fresh()->status);

        // 4. Duplicate bulk assignment request on already-assigned $parcelNorth is rejected so it is not double-processed
        $duplicateAssign = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-assign-rider'), [
            'confirmed' => true,
            'mode' => 'manual',
            'rider_id' => $riderNorth->id,
            'delivery_ids' => [$parcelNorth->id],
        ]);
        $duplicateAssign->assertStatus(422)
            ->assertJsonPath('succeeded', 0)
            ->assertJsonPath('failed', 1);
    }

    public function test_selection_metadata_and_cross_company_security_prevent_unauthorized_or_repeated_processing(): void
    {
        $otherCompany = $this->company('External Carrier');
        $otherHub = $this->center($otherCompany, 'External Hub');

        $foreignParcel = $this->delivery([
            'tracking_number' => 'TRK-20261112-4001',
            'center_id' => $otherHub->id,
            'origin_center_id' => $otherHub->id,
            'parcel_status' => 'pending_arrival',
        ]);

        $localPending = $this->delivery([
            'tracking_number' => 'TRK-20261112-4002',
            'center_id' => $this->hubA->id,
            'origin_center_id' => $this->hubA->id,
            'parcel_status' => 'pending_arrival',
        ]);

        // Cross-company parcel is rejected during bulk receive while local eligible parcel succeeds
        $receiveResp = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-receive'), [
            'confirmed' => true,
            'delivery_ids' => [$localPending->id, $foreignParcel->id],
        ]);

        $receiveResp->assertStatus(207)
            ->assertJsonPath('succeeded', 1)
            ->assertJsonPath('failed', 1);

        $this->assertSame('received', $localPending->fresh()->parcel_status);
        $this->assertSame('pending_arrival', $foreignParcel->fresh()->parcel_status);

        // Repeated bulk receive on the same parcel is safely rejected (idempotency / duplicate protection)
        $repeatReceive = $this->actingAs($this->hubStaff)->postJson(route('operations.bulk-receive'), [
            'confirmed' => true,
            'delivery_ids' => [$localPending->id],
        ]);

        $repeatReceive->assertStatus(422)
            ->assertJsonPath('succeeded', 0)
            ->assertJsonPath('failed', 1);

        // Verify frontend data-parcels metadata contains hub names, service area names, eligibility, and ineligibility reasons
        $page = $this->actingAs($this->hubStaff)->get(route('operations.index'));
        $page->assertOk();
        preg_match("/data-parcels='([^']+)'/", $page->getContent(), $matches);
        $meta = collect(json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR))->keyBy('id');

        $this->assertArrayHasKey($localPending->id, $meta);
        $this->assertSame($this->hubA->id, $meta[$localPending->id]['center_id']);
        $this->assertSame($this->hubA->name, $meta[$localPending->id]['current_hub_name']);
        $this->assertNotEmpty($meta[$localPending->id]['ineligible_reasons']['receive']);
        $this->assertNotEmpty($meta[$localPending->id]['ineligible_reasons']['sort']);
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    public function test_operations_includes_pickup_review_assignment_and_waiting_parcels_without_granting_hub_custody(): void
    {
        $approved = [];
        for ($i = 0; $i < 3; $i++) {
            $approved[] = $this->pickupParcel('approved');
        }
        $review = $this->pickupParcel('pending');
        $waiting = $this->pickupParcel('pending', 'pending');
        $rejected = $this->pickupParcel('rejected');
        $sorted = $this->delivery(['center_id' => $this->hubA->id, 'destination_center_id' => $this->hubA->id, 'parcel_status' => 'sorted']);
        $foreign = $this->pickupParcel('approved', 'ready_for_delivery', $this->hubB);
        $data = $this->operationsView($this->hubStaff)->getData();
        $rows = $data['actionParcels']->keyBy('delivery.id');
        $this->assertSame(5, $data['counts']['action_parcels']);
        $this->assertSame(7, $data['parcelPaginator']->total());
        foreach ($approved as $parcel) {
            $this->assertSame('assign_pickup_rider', $rows[$parcel->id]['workflow']['next']['key']);
            $this->assertTrue($rows[$parcel->id]['workflow']['next']['enabled']);
            $this->assertFalse($rows[$parcel->id]['eligibility']['receive']);
            $this->assertFalse($rows[$parcel->id]['eligibility']['assign_rider']);
            $this->assertSame('At seller; pickup not completed', $rows[$parcel->id]['workflow']['custody']);
        }
        $this->assertSame('review_pickup', $rows[$review->id]['workflow']['next']['key']);
        $this->assertSame('awaiting_seller', $rows[$waiting->id]['workflow']['key']);
        $this->assertSame('pickup_rejected', $rows[$rejected->id]['workflow']['key']);
        $this->assertFalse($rows[$waiting->id]['workflow']['next']['enabled']);
        $this->assertFalse($rows[$rejected->id]['workflow']['next']['enabled']);
        $this->assertArrayNotHasKey($foreign->id, $rows->all());
        $this->assertSame(3, $this->operationsView($this->hubStaff, ['parcel_status' => 'pickup_assignment'])->getData()['actionParcels']->count());
        $this->assertSame([$review->id], $this->operationsView($this->hubStaff, ['parcel_status' => 'pickup_review'])->getData()['actionParcels']->pluck('delivery.id')->all());
    }

    public function test_operations_paginates_all_work_instead_of_dropping_parcels_after_fifteen(): void
    {
        $ids = [];
        for ($i = 0; $i < 32; $i++) {
            $ids[] = $this->delivery(['center_id' => $this->hubA->id, 'parcel_status' => 'received'])->id;
        }
        $first = $this->operationsView($this->hubStaff)->getData();
        $second = $this->operationsView($this->hubStaff, ['parcels_page' => 2])->getData();
        $this->assertSame(32, $first['counts']['action_parcels']);
        $this->assertSame(32, $first['parcelPaginator']->total());
        $this->assertCount(25, $first['actionParcels']);
        $this->assertCount(7, $second['actionParcels']);
        $this->assertSame($ids, $first['actionParcels']->concat($second['actionParcels'])->pluck('delivery.id')->all());
    }

    public function test_pickup_review_can_be_processed_from_operations_and_returns_to_the_workspace(): void
    {
        $parcel = $this->pickupParcel('pending');
        $this->actingAs($this->hubStaff)->get(route('operations.index'))->assertOk()
            ->assertSee('Approve pickup')->assertSee(route('pickup-requests.approve', $parcel->pickupRequest), false);
        $this->actingAs($this->hubStaff)->from(route('operations.index'))
            ->post(route('pickup-requests.approve', $parcel->pickupRequest))->assertRedirect(route('operations.index'));
        $this->assertSame('approved', $parcel->pickupRequest()->first()->status);
        $this->actingAs($this->hubStaff)->get(route('operations.index'))->assertOk()->assertSee('Auto-assign pickup rider');
        $this->assertNull($parcel->fresh()->received_at);
        $this->assertSame('pending_arrival', $parcel->fresh()->parcel_status);
        $this->assertSame(0, $this->operationsView($this->hubStaff, ['parcel_status' => 'awaiting_receiving'])->getData()['actionParcels']->count());
    }

    public function test_pickup_rider_assignment_from_workspace_preserves_review_and_hub_guards(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('method')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
        $approved = $this->pickupParcel('approved');
        $pending = $this->pickupParcel('pending');
        $foreign = $this->pickupParcel('approved', 'ready_for_delivery', $this->hubB);
        $rider = Rider::create([
            'center_id' => $this->hubA->id, 'name' => 'Pickup Rider', 'vehicle_type' => 'Motorcycle',
            'vehicle_verification' => 'verified', 'status' => 'active', 'is_online' => true,
            'is_verified' => true, 'approved_at' => now()->subDay(), 'license_expiry' => now()->addYear()->toDateString(),
        ]);
        $this->actingAs($this->hubStaff)->get(route('operations.index'))->assertOk()->assertSee('Assign selected rider');
        $this->actingAs($this->hubStaff)->from(route('operations.index'))
            ->post(route('deliveries.assign-rider', $pending), ['rider_id' => $rider->id])->assertStatus(409);
        $this->assertNull($pending->fresh()->rider_id);
        $this->actingAs($this->hubStaff)->from(route('operations.index'))
            ->post(route('deliveries.assign-rider', $foreign), ['rider_id' => $rider->id])->assertForbidden();
        $this->actingAs($this->hubStaff)->from(route('operations.index'))
            ->post(route('deliveries.assign-rider', $approved), ['rider_id' => $rider->id])
            ->assertSessionHasNoErrors()->assertRedirect(route('operations.index'));
        $this->assertSame($rider->id, $approved->fresh()->rider_id);
        $this->assertSame('assigned', $approved->fresh()->status);
        $this->assertNull($approved->fresh()->received_at);
        $rows = $this->operationsView($this->hubStaff)->getData()['actionParcels']->keyBy('delivery.id');
        $this->assertSame('pickup_in_progress', $rows[$approved->id]['workflow']['key']);
        $this->assertFalse($rows[$approved->id]['eligibility']['receive']);
    }

    private function pickupParcel(string $status, string $orderStatus = 'ready_for_delivery', ?LogisticsCenter $hub = null): Delivery
    {
        $hub ??= $this->hubA;
        $orderId = DB::table('orders')->insertGetId(['status' => $orderStatus, 'created_at' => now(), 'updated_at' => now()]);
        $delivery = $this->delivery(['center_id' => $hub->id, 'order_id' => $orderId, 'weight' => 2]);
        \App\Models\PickupRequest::create(['delivery_id' => $delivery->id, 'center_id' => $hub->id, 'status' => $status, 'requested_at' => now(), 'rejection_reason' => $status === 'rejected' ? 'Pickup details need correction' : null]);
        return $delivery;
    }

    private function operationsView(User $user, array $query = [])
    {
        $request = Request::create('/logistics/operations', 'GET', $query);
        $request->setUserResolver(fn () => $user);

        return app(OperationsController::class)->index($request);
    }

    private function seedFacilities(): void
    {
        $this->company = LogisticsCompany::create(['name' => 'INVOIZ Logistics', 'status' => 'active']);

        $this->hubA = $this->center($this->company, 'Hub A');
        $this->hubB = $this->center($this->company, 'Hub B');

        $this->hubStaff = $this->user('hub.staff@example.test', $this->hubA);
    }

    private function company(string $name): LogisticsCompany
    {
        return LogisticsCompany::create(['name' => $name, 'status' => 'active']);
    }

    private function center(LogisticsCompany $company, string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name,
            'address' => '1 Test Road',
            'is_active' => true,
            'logistics_company_id' => $company->id,
        ]);
    }

    private function user(string $email, LogisticsCenter $center, ?LogisticsCompany $company = null): User
    {
        return User::create([
            'name' => 'Hub Staff',
            'first_name' => 'Hub',
            'last_name' => 'Staff',
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => 'staff',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'center_id' => $center->id,
            'logistics_company_id' => ($company ?? $this->company)->id,
        ]);
    }

    private static int $trackingSequence = 0;

    private function delivery(array $attributes = []): Delivery
    {
        self::$trackingSequence++;

        return Delivery::create(array_merge([
            'tracking_number' => 'TRK-OPS-'.str_pad((string) self::$trackingSequence, 4, '0', STR_PAD_LEFT),
            'sender_name' => 'Sender',
            'recipient_name' => 'Recipient',
            'sender_address' => '1 Sender Street',
            'recipient_address' => '2 Recipient Street',
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
        ], $attributes));
    }

    private function transfer(LogisticsCenter $origin, LogisticsCenter $destination, string $status): HubTransfer
    {
        return HubTransfer::create([
            'reference' => 'HT-OPS-'.uniqid(),
            'logistics_company_id' => $origin->logistics_company_id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => $status,
        ]);
    }

    private function entry(
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

    private function createSchema(): void
    {
        $schema = Schema::connection('sqlite');

        foreach ([
            'hub_transfer_events', 'hub_transfer_parcels', 'hub_transfers',
            'delivery_status_logs', 'deliveries', 'orders', 'pickup_requests', 'logistics_trucks',
            'hub_transport_drivers', 'riders', 'logistics_centers',
            'logistics_companies', 'users',
        ] as $table) {
            $schema->dropIfExists($table);
        }

        $schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('pending');
            $table->string('shipping_city')->nullable();
            $table->string('shipping_address')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('staff');
            $table->boolean('is_admin')->default(false);
            $table->string('status')->default('active');
            $table->string('account_status')->default('active');
            $table->string('phone')->nullable();
            $table->string('profile_picture')->nullable();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->rememberToken();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });

        $schema->create('logistics_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('logistics_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->timestamps();
        });

        $schema->create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_center_id');
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('riders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->decimal('vehicle_capacity_kg', 8, 2)->nullable();
            $table->string('license_plate')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('vehicle_verification')->default('verified');
            $table->string('status')->default('active');
            $table->boolean('is_online')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        $schema->create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('logistics_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('photo_path')->nullable();
            $table->text('notifications')->nullable();
            $table->boolean('email_notifications')->default(true);
            $table->text('delivery')->nullable();
            $table->text('preferences')->nullable();
            $table->timestamps();
        });

        $schema->create('logistics_trucks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->integer('parcel_capacity')->default(0);
            $table->decimal('capacity_kg', 10, 2)->default(0);
            $table->string('status')->default('available');
            $table->timestamps();
        });

        $schema->create('hub_transport_drivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('logistics_truck_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->string('status')->default('available');
            $table->boolean('is_online')->default(false);
            $table->timestamps();
        });

        $schema->create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('origin_center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('status')->default('waiting_for_rider');
            $table->string('parcel_status')->nullable();
            $table->string('priority')->nullable();
            $table->string('package_type')->nullable();
            $table->string('package_description')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->string('sender_address')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_address')->nullable();
            $table->string('recipient_city')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('amount_to_collect', 10, 2)->nullable();
            $table->decimal('cod_amount_received', 10, 2)->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('pickup_pin')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->string('archive_note')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('sorting_center_handoff_at')->nullable();
            $table->unsignedBigInteger('sorting_center_handoff_rider_id')->nullable();
            $table->timestamp('sorting_center_pickup_at')->nullable();
            $table->unsignedBigInteger('sorting_center_pickup_rider_id')->nullable();
            $table->timestamp('inter_hub_dispatched_at')->nullable();
            $table->timestamps();
        });

        $schema->create('pickup_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('center_id')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('status');
            $table->string('location')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });

        $schema->create('hub_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('origin_center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('truck_id')->nullable();
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->string('status')->default('pending');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('dispatched_by')->nullable();
            $table->unsignedBigInteger('arrived_by')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('planned_dispatch_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('origin_arrived_at')->nullable();
            $table->timestamp('destination_arrived_at')->nullable();
            $table->timestamps();
        });

        $schema->create('hub_transfer_parcels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_transfer_id');
            $table->unsignedBigInteger('delivery_id');
            $table->string('loading_status')->default('pending');
            $table->unsignedBigInteger('loaded_by')->nullable();
            $table->unsignedBigInteger('loaded_by_hub_driver_id')->nullable();
            $table->timestamp('loaded_at')->nullable();
            $table->string('receiving_status')->default('pending');
            $table->string('condition')->nullable();
            $table->text('exception_reason')->nullable();
            $table->unsignedBigInteger('received_by')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        $schema->create('hub_transfer_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_transfer_id');
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->string('event_type');
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('hub_transport_driver_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->timestamps();
        });
    }
}
