<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryItem;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransfer;
use App\Models\HubTransferEvent;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\ServiceArea;
use App\Models\User;
use App\Support\LogisticsAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Parcel-detail privacy across companies.
 *
 * A destination company may open an incoming parcel it is about to receive and
 * must see the tracking number, transfer route, hub states and receiving
 * inspection. It must never receive the origin company's fleet plates,
 * transport driver, sorting riders, or origin-authored event actor names and
 * notes in the rendered page. Origin staff and global oversight keep the full
 * record.
 *
 * Runs on a self-contained in-memory SQLite schema, so no MySQL instance is
 * touched and no migrator runs.
 */
class DeliveryParcelDetailPrivacyTest extends TestCase
{
    private LogisticsCompany $originCompany;
    private LogisticsCompany $destinationCompany;
    private LogisticsCompany $foreignCompany;

    private LogisticsCenter $originHub;
    private LogisticsCenter $destinationHub;
    private LogisticsCenter $foreignHub;

    private User $globalAdmin;
    private User $originStaff;
    private User $destinationStaff;
    private User $foreignStaff;

    private Delivery $delivery;
    private HubTransfer $transfer;

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
        $this->seedNetwork();
        $this->seedTransfer();
    }

    // ------------------------------------------------------------------
    // Destination company: permitted workflow without origin-private data
    // ------------------------------------------------------------------

    public function test_destination_staff_open_the_incoming_parcel_without_origin_private_detail(): void
    {
        $response = $this->actingAs($this->destinationStaff)->get(route('deliveries.show', $this->delivery));

        $response->assertOk();
        $body = $response->getContent();

        // Permitted: parcel identification, route, hub states, custody facts.
        $this->assertStringContainsString('TRK-PRIV-0001', $body);
        $this->assertStringContainsString('HT-PRIV-1', $body);
        $this->assertStringContainsString('Origin Hub', $body);
        $this->assertStringContainsString('Destination Hub', $body);
        $this->assertStringContainsString('Transfer dispatched', $body);
        $this->assertStringContainsString('Physical arrival at destination hub', $body);
        $this->assertStringContainsString('Receiving inspection — custody accepted', $body);
        $this->assertStringContainsString('Condition: good', $body);
        $this->assertStringContainsString('Destination Receiver', $body);
        $this->assertStringContainsString('Changed by: Destination Staff', $body);
        $this->assertStringContainsString('Handed to Hub:', $body);
        $this->assertStringContainsString('Hub transport', $body);

        // Rejected: origin fleet, transport driver, origin personnel, notes.
        $this->assertStringNotContainsString('ORIGIN-PLATE-9911', $body);
        $this->assertStringNotContainsString('ORIGIN-PLATE-9922', $body);
        $this->assertStringNotContainsString('Origin Driver Private', $body);
        $this->assertStringNotContainsString('Origin Staff Person', $body);
        $this->assertStringNotContainsString('Origin Creator Private', $body);
        $this->assertStringNotContainsString('Origin Reviewer Private', $body);
        $this->assertStringNotContainsString('Inter Hub Rider Private', $body);
        $this->assertStringNotContainsString('Handoff Rider Private', $body);
        $this->assertStringNotContainsString('Origin Hub Dispatcher', $body);
        $this->assertStringNotContainsString('Loaded by Origin Staff Person', $body);
        $this->assertStringNotContainsString('Assigned ORIGIN-PLATE-9911', $body);
        $this->assertStringNotContainsString('Reassigned to ORIGIN-PLATE-9922', $body);
        $this->assertStringNotContainsString('Reassigned to Origin Driver Private', $body);
        $this->assertStringNotContainsString('Handed off by Handoff Rider Private', $body);
        $this->assertStringNotContainsString('Fleet truck reassigned', $body);
        $this->assertStringNotContainsString('Fleet driver reassigned', $body);
        $this->assertStringNotContainsString('Truck Assigned', $body);
        $this->assertStringNotContainsString('Driver Assigned', $body);

        // Rider-assignment notes written by the origin company stay private.
        $this->assertStringNotContainsString('Origin Assign Rider', $body);
        $this->assertStringNotContainsString('ASSIGN-PLATE-7711', $body);
        $this->assertStringNotContainsString('Origin Suspended Rider', $body);
    }

    // ------------------------------------------------------------------
    // Origin company keeps its own operational record
    // ------------------------------------------------------------------

    public function test_origin_staff_keep_full_origin_fleet_and_event_detail(): void
    {
        $response = $this->actingAs($this->originStaff)->get(route('deliveries.show', $this->delivery));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('ORIGIN-PLATE-9911', $body);
        $this->assertStringContainsString('ORIGIN-PLATE-9922', $body);
        $this->assertStringContainsString('Origin Driver Private', $body);
        $this->assertStringContainsString('Origin Staff Person', $body);
        $this->assertStringContainsString('Origin Hub Dispatcher', $body);
        $this->assertStringContainsString('Origin Creator Private', $body);
        $this->assertStringContainsString('Origin Reviewer Private', $body);
        $this->assertStringContainsString('Inter Hub Rider Private', $body);
        $this->assertStringContainsString('Handoff Rider Private', $body);
        $this->assertStringContainsString('Loaded by Origin Staff Person', $body);
        $this->assertStringContainsString('Assigned ORIGIN-PLATE-9911', $body);
        $this->assertStringContainsString('Reassigned to ORIGIN-PLATE-9922', $body);
        $this->assertStringContainsString('Reassigned to Origin Driver Private', $body);
        $this->assertStringContainsString('Handed off by Handoff Rider Private', $body);
        $this->assertStringContainsString('Fleet truck reassigned', $body);
        $this->assertStringContainsString('Fleet driver reassigned', $body);
        $this->assertStringContainsString('Truck Assigned', $body);
        $this->assertStringContainsString('Driver Assigned', $body);
        $this->assertStringContainsString('Origin Assign Rider', $body);
        $this->assertStringContainsString('ASSIGN-PLATE-7711', $body);
        $this->assertStringContainsString('Origin Suspended Rider', $body);

        // Another company's staff identity is not origin-visible.
        $this->assertStringNotContainsString('Changed by: Destination Staff', $body);
    }

    // ------------------------------------------------------------------
    // Global oversight keeps the complete record
    // ------------------------------------------------------------------

    public function test_global_oversight_keeps_the_complete_record(): void
    {
        $response = $this->actingAs($this->globalAdmin)->get(route('deliveries.show', $this->delivery));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('ORIGIN-PLATE-9911', $body);
        $this->assertStringContainsString('Origin Driver Private', $body);
        $this->assertStringContainsString('Origin Staff Person', $body);
        $this->assertStringContainsString('Inter Hub Rider Private', $body);
        $this->assertStringContainsString('Origin Creator Private', $body);
        $this->assertStringContainsString('Origin Reviewer Private', $body);
        $this->assertStringContainsString('Condition: good', $body);
        $this->assertStringContainsString('Destination Receiver', $body);
        $this->assertStringContainsString('Origin Assign Rider', $body);
        $this->assertStringContainsString('Origin Suspended Rider', $body);
    }

    // ------------------------------------------------------------------
    // Unrelated companies stay locked out
    // ------------------------------------------------------------------

    public function test_unrelated_company_staff_cannot_open_the_parcel(): void
    {
        $this->actingAs($this->foreignStaff)
            ->get(route('deliveries.show', $this->delivery))
            ->assertForbidden();
    }

    // ------------------------------------------------------------------
    // Route origin stays stable after the parcel changes hub
    // ------------------------------------------------------------------

    public function test_order_backed_parcel_after_destination_receipt_keeps_origin_private_hidden(): void
    {
        $this->delivery->update([
            'center_id' => $this->destinationHub->id,
            'parcel_status' => 'received',
            'received_at' => now(),
            'scanned_at' => null,
            'sorted_at' => null,
            'dispatched_at' => null,
        ]);
        $this->assertSame($this->originHub->id, $this->delivery->fresh()->routeOriginId());

        $response = $this->actingAs($this->destinationStaff)
            ->get(route('deliveries.show', $this->delivery->fresh()));

        $response->assertOk();
        $body = $response->getContent();

        $this->assertStringNotContainsString('ORIGIN-PLATE-9911', $body);
        $this->assertStringNotContainsString('Origin Driver Private', $body);
        $this->assertStringNotContainsString('Origin Assign Rider', $body);
        $this->assertStringNotContainsString('ASSIGN-PLATE-7711', $body);
        $this->assertStringContainsString('Parcel received at Destination Hub receiving bay.', $body);
    }

    public function test_standalone_parcel_with_null_origin_uses_the_transfer_leg_origin_after_receipt(): void
    {
        $delivery = $this->standaloneParcelReceivedAtDestination('TRK-PRIV-0002', $this->originHub->id, 'Standalone Handoff Rider');

        $this->assertNull($delivery->origin_center_id);
        $this->assertSame($this->originHub->id, $delivery->routeOriginId());

        $response = $this->actingAs($this->destinationStaff)->get(route('deliveries.show', $delivery));

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('Standalone Handoff Rider', $body);
        $this->assertStringContainsString('TRK-PRIV-0002', $body);
    }

    public function test_legacy_parcel_with_unknown_origin_fails_closed_for_company_viewers(): void
    {
        $delivery = $this->standaloneParcelReceivedAtDestination('TRK-PRIV-0003', null, 'Unknown Origin Rider');

        $this->assertNull($delivery->routeOriginId());
        $this->assertFalse(LogisticsAccess::canViewOriginPrivateAt($this->destinationStaff, $delivery->routeOriginId()));
        $this->assertFalse(LogisticsAccess::canViewOriginPrivateAt($this->originStaff, $delivery->routeOriginId()));
        $this->assertTrue(LogisticsAccess::canViewOriginPrivateAt($this->globalAdmin, $delivery->routeOriginId()));

        $response = $this->actingAs($this->destinationStaff)->get(route('deliveries.show', $delivery));

        $response->assertOk();
        $this->assertStringNotContainsString('Unknown Origin Rider', $response->getContent());

        // A received legacy row without any movement record cannot prove its
        // origin either: the destination hub must not be treated as the origin.
        $unrecorded = Delivery::create([
            'tracking_number' => 'TRK-PRIV-0004',
            'order_id' => null,
            'center_id' => $this->destinationHub->id,
            'origin_center_id' => null,
            'destination_center_id' => $this->destinationHub->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'received_at' => now(),
            'created_by' => $this->originStaff->id,
        ]);
        DeliveryStatusLog::create([
            'delivery_id' => $unrecorded->id,
            'status' => 'sorting_center_handoff',
            'notes' => 'Handed off by Unknown Origin Rider to Origin Hub.',
            'changed_by' => $this->originStaff->id,
        ]);

        $this->assertNull($unrecorded->routeOriginId());
        $this->assertStringNotContainsString(
            'Unknown Origin Rider',
            $this->actingAs($this->destinationStaff)->get(route('deliveries.show', $unrecorded))->getContent()
        );
    }

    public function test_new_standalone_delivery_persists_its_origin_hub(): void
    {
        $response = $this->actingAs($this->originStaff)->post(route('deliveries.store'), [
            'sender_name' => 'New Standalone Sender',
            'sender_phone' => '09171234567',
            'sender_address' => '1 Origin Street',
            'recipient_name' => 'New Standalone Recipient',
            'recipient_phone' => '09181234567',
            'recipient_address' => '2 Destination Street, Destination City',
            'package_type' => 'Parcel',
            'weight' => 1.5,
        ]);

        $response->assertRedirect();

        $delivery = Delivery::where('sender_name', 'New Standalone Sender')->firstOrFail();
        $this->assertNull($delivery->order_id);
        $this->assertSame($this->originHub->id, (int) $delivery->center_id);
        $this->assertSame($this->originHub->id, (int) $delivery->origin_center_id);
        $this->assertSame($this->originHub->id, $delivery->routeOriginId());
    }

    public function test_destination_authored_receiving_notes_stay_visible(): void
    {
        DeliveryStatusLog::create([
            'delivery_id' => $this->delivery->id,
            'status' => 'waiting_for_rider',
            'location' => 'Destination Hub',
            'notes' => 'Destination note: last-mile rider queue acknowledged.',
            'changed_by' => $this->destinationStaff->id,
        ]);

        $response = $this->actingAs($this->destinationStaff)->get(route('deliveries.show', $this->delivery));

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Destination note: last-mile rider queue acknowledged.', $body);
        $this->assertStringContainsString('Parcel received at Destination Hub receiving bay.', $body);
        $this->assertStringNotContainsString('Assigned to rider Origin Assign Rider', $body);
    }

    // ------------------------------------------------------------------
    // Alternate surfaces (tracking page, scanner API, dashboard, rider API)
    // ------------------------------------------------------------------

    public function test_tracking_search_hides_origin_assignment_notes_and_actor_names(): void
    {
        $delivery = $this->receivedParcelWithCrossCompanyLogs('TRK-PRIV-2001');
        $assignedAt = $delivery->statusLogs()->where('status', 'assigned')->firstOrFail()->created_at;

        $response = $this->actingAs($this->destinationStaff)
            ->get(route('tracking.search', ['tracking_number' => 'TRK-PRIV-2001']));

        $response->assertOk();
        $body = $response->getContent();

        // Public tracking facts, destination custody and the hidden note's own
        // status label and timestamp remain.
        $this->assertStringContainsString('TRK-PRIV-2001', $body);
        $this->assertStringContainsString('Assigned', $body);
        $this->assertStringContainsString('Waiting for Rider', $body);
        $this->assertStringContainsString('Destination receiving inspection — custody accepted.', $body);
        $this->assertStringContainsString('Changed by: Destination Staff', $body);
        $this->assertStringContainsString($assignedAt->format('M d, Y \a\t g:i A'), $body);

        // Origin assignment notes, origin actors and origin creator stay private.
        $this->assertStringNotContainsString('ASSIGN-PLATE-7711', $body);
        $this->assertStringNotContainsString('Origin Assign Rider', $body);
        $this->assertStringNotContainsString('Origin Suspended Rider', $body);
        $this->assertStringNotContainsString('Unassigned from suspended rider', $body);
        $this->assertStringNotContainsString('Handed off by Origin Assignment Writer', $body);
        $this->assertStringNotContainsString('Origin Staff Person', $body);
    }

    public function test_origin_and_global_tracking_keep_assignment_detail(): void
    {
        foreach ([$this->originStaff, $this->globalAdmin] as $viewer) {
            $response = $this->actingAs($viewer)
                ->get(route('tracking.search', ['tracking_number' => 'TRK-PRIV-0001']));

            $response->assertOk();
            $body = $response->getContent();
            $this->assertStringContainsString('ASSIGN-PLATE-7711', $body);
            $this->assertStringContainsString('Origin Suspended Rider', $body);
            $this->assertStringContainsString('Changed by: Origin Hub Dispatcher', $body);
            $this->assertStringContainsString('Origin Creator Private', $body);
        }
    }

    public function test_scan_history_hides_origin_assignment_detail_and_keeps_destination_custody(): void
    {
        $this->receivedParcelWithCrossCompanyLogs('TRK-20261101-SC02');
        Sanctum::actingAs($this->destinationStaff, ['logistics']);

        $lookup = $this->postJson('/api/logistics/scan/lookup', ['tracking_number' => 'TRK-20261101-SC02']);
        $lookup->assertOk();
        $lookupBody = $lookup->getContent();
        $this->assertStringNotContainsString('ASSIGN-PLATE-7711', $lookupBody);
        $this->assertStringNotContainsString('Origin Suspended Rider', $lookupBody);
        $this->assertStringNotContainsString('Origin Staff Person', $lookupBody);
        $this->assertStringNotContainsString('Origin Assignment Writer', $lookupBody);

        $history = collect($lookup->json('history'));
        $assigned = $history->firstWhere('status', 'assigned');
        $this->assertNotNull($assigned);
        $this->assertNull($assigned['notes']);
        $this->assertNull($assigned['actor']);

        $handoff = $history->firstWhere('status', 'sorting_center_handoff');
        $this->assertNotNull($handoff);
        $this->assertNull($handoff['notes']);
        $this->assertNull($handoff['actor']);

        $received = $history->firstWhere('status', 'received');
        $this->assertNotNull($received);
        $this->assertSame('Destination receiving inspection — custody accepted.', $received['notes']);
        $this->assertSame('Destination Staff', $received['actor']);

        $confirm = $this->postJson('/api/logistics/scan/confirm', ['tracking_number' => 'TRK-20261101-SC02']);
        $confirm->assertOk();
        $confirmBody = $confirm->getContent();
        $this->assertStringNotContainsString('ASSIGN-PLATE-7711', $confirmBody);
        $this->assertStringNotContainsString('Origin Suspended Rider', $confirmBody);

        $scanned = collect($confirm->json('history'))->firstWhere('status', 'scanned');
        $this->assertNotNull($scanned);
        $this->assertSame('Parcel scanned and verified via mobile scanner.', $scanned['notes']);
        $this->assertSame('Destination Staff', $scanned['actor']);
    }

    public function test_dashboard_activity_redacts_origin_actor_names(): void
    {
        $this->receivedParcelWithCrossCompanyLogs('TRK-PRIV-2003');

        $response = $this->actingAs($this->destinationStaff)->get(route('dashboard'));
        $response->assertOk();
        $this->assertStringNotContainsString('Origin Staff Person', $response->getContent());

        $view = app(\App\Http\Controllers\Logistics\DashboardController::class)
            ->index(\Illuminate\Http\Request::create(route('dashboard'), 'GET'));
        $activities = collect($view->getData()['activities']);

        $assigned = $activities->firstWhere('status', 'assigned');
        $this->assertNotNull($assigned, 'The assignment event must still appear in the activity feed.');
        $this->assertNull($assigned['by']);

        $received = $activities->firstWhere('status', 'received');
        $this->assertNotNull($received);
        $this->assertSame('Destination Staff', $received['by']);
    }

    public function test_destination_rider_detail_hides_origin_assignment_notes(): void
    {
        [$riderUser, $rider] = $this->riderAccount('Destination Rider', 'destination.rider@pd.test', $this->destinationHub);
        $delivery = $this->receivedParcelWithCrossCompanyLogs('TRK-PRIV-2004');
        $delivery->update(['rider_id' => $rider->id, 'status' => 'assigned']);

        Sanctum::actingAs($riderUser, ['rider']);
        $response = $this->getJson('/api/logistics/rider/deliveries/'.$delivery->id);

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('ASSIGN-PLATE-7711', $body);
        $this->assertStringNotContainsString('Origin Suspended Rider', $body);
        $this->assertStringNotContainsString('Origin Assignment Writer', $body);

        $logs = collect($response->json('delivery.status_logs'));
        $assigned = $logs->firstWhere('status', 'assigned');
        $this->assertNotNull($assigned);
        $this->assertNull($assigned['notes']);
        $this->assertNotNull($logs->firstWhere('status', 'received'));

        // Legitimate delivery detail for the rider's own job stays intact.
        $this->assertSame('TRK-PRIV-2004', $response->json('delivery.tracking_number'));
        $this->assertSame('Cross Company Recipient', $response->json('delivery.customer.name'));
        $this->assertSame('Destination rider collected the parcel.', $logs->firstWhere('status', 'picked_up')['notes']);
    }

    public function test_companyless_viewer_and_author_do_not_gain_same_company_note_visibility(): void
    {
        $legacyHub = LogisticsCenter::create([
            'name' => 'Legacy Hub',
            'code' => 'LGC',
            'address' => '4 Legacy Street',
            'city' => 'Legacy City',
            'is_active' => true,
            'logistics_company_id' => null,
        ]);
        $legacyStaff = User::create([
            'name' => 'Legacy Hub Staff',
            'email' => 'legacy.staff@pd.test',
            'password' => Hash::make('secret'),
            'role' => 'staff',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => null,
            'center_id' => $legacyHub->id,
        ]);

        $assignedAt = now()->subMinutes(20)->seconds(0);
        $delivery = Delivery::create([
            'tracking_number' => 'TRK-20261101-CL05',
            'order_id' => null,
            'rider_id' => null,
            'center_id' => $legacyHub->id,
            'origin_center_id' => $legacyHub->id,
            'destination_center_id' => $legacyHub->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'sender_name' => 'Legacy Sender',
            'recipient_name' => 'Legacy Recipient',
            'created_by' => $legacyStaff->id,
            'received_at' => now(),
        ]);
        $this->statusLogAt($delivery, $assignedAt, [
            'status' => 'assigned',
            'location' => 'Legacy Origin Desk',
            'notes' => 'Assigned to rider Legacy Rider (Motorcycle, plate LEGACY-PLATE-1).',
            'changed_by' => $legacyStaff->id,
        ]);
        $this->statusLogAt($delivery, now()->subMinutes(5)->seconds(0), [
            'status' => 'received',
            'location' => 'Legacy Receiving Bay',
            'notes' => 'Legacy receiving inspection complete.',
            'changed_by' => $legacyStaff->id,
        ]);

        $detail = $this->actingAs($legacyStaff)->get(route('deliveries.show', $delivery));
        $detail->assertOk();
        $detailBody = $detail->getContent();
        $this->assertStringContainsString('Legacy receiving inspection complete.', $detailBody);
        $this->assertStringContainsString('Legacy Origin Desk', $detailBody);
        $this->assertStringContainsString($assignedAt->format('M d, Y · h:i A'), $detailBody);
        $this->assertStringContainsString('Assigned', $detailBody);
        $this->assertStringNotContainsString('LEGACY-PLATE-1', $detailBody);
        $this->assertStringNotContainsString('Assigned to rider Legacy Rider', $detailBody);

        $tracking = $this->actingAs($legacyStaff)
            ->get(route('tracking.search', ['tracking_number' => 'TRK-20261101-CL05']));
        $tracking->assertOk();
        $this->assertStringContainsString('Legacy receiving inspection complete.', $tracking->getContent());
        $this->assertStringNotContainsString('LEGACY-PLATE-1', $tracking->getContent());

        Sanctum::actingAs($legacyStaff, ['logistics']);
        $scan = $this->postJson('/api/logistics/scan/lookup', ['tracking_number' => 'TRK-20261101-CL05']);
        $scan->assertOk();
        $this->assertStringNotContainsString('LEGACY-PLATE-1', $scan->getContent());

        $scanHistory = collect($scan->json('history'));
        $this->assertNull($scanHistory->firstWhere('status', 'assigned')['notes']);
        $this->assertSame('Legacy receiving inspection complete.', $scanHistory->firstWhere('status', 'received')['notes']);
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /**
     * Standalone parcel already received at the destination hub. Its persisted
     * origin is null; the only movement evidence is the fleet-transfer leg.
     */
    private function standaloneParcelReceivedAtDestination(string $tracking, ?int $transferOriginId, string $riderName): Delivery
    {
        $rider = $this->rider($riderName, $this->originHub);

        $delivery = Delivery::create([
            'tracking_number' => $tracking,
            'order_id' => null,
            'rider_id' => null,
            'center_id' => $this->destinationHub->id,
            'origin_center_id' => null,
            'destination_center_id' => $this->destinationHub->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'sender_name' => 'Standalone Sender',
            'sender_address' => '1 Origin Street',
            'recipient_name' => 'Cross Company Recipient',
            'recipient_address' => '2 Destination Street, Destination City',
            'created_by' => $this->originStaff->id,
            'received_at' => now(),
            'sorting_center_handoff_at' => now()->subDay(),
            'sorting_center_handoff_rider_id' => $rider->id,
        ]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-'.$tracking,
            'logistics_company_id' => $transferOriginId !== null ? $this->originCompany->id : null,
            'origin_center_id' => $transferOriginId,
            'destination_center_id' => $this->destinationHub->id,
            'status' => 'received',
            'created_by' => $this->originStaff->id,
            'dispatched_at' => now()->subHours(4),
            'arrived_at' => now()->subHours(2),
            'received_at' => now()->subHour(),
        ]);

        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'received',
            'received_at' => now()->subHour(),
        ]);

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => 'sorting_center_handoff',
            'location' => 'Origin Hub',
            'notes' => "Handed off by {$riderName} to Origin Hub.",
            'changed_by' => $this->originStaff->id,
        ]);

        return $delivery;
    }

    private function seedNetwork(): void
    {
        $this->originCompany = LogisticsCompany::create(['name' => 'Origin Logistics', 'status' => 'active']);
        $this->destinationCompany = LogisticsCompany::create(['name' => 'Destination Logistics', 'status' => 'active']);
        $this->foreignCompany = LogisticsCompany::create(['name' => 'Foreign Logistics', 'status' => 'active']);

        $this->originHub = LogisticsCenter::create([
            'name' => 'Origin Hub',
            'code' => 'ORG',
            'address' => '1 Origin Street',
            'city' => 'Origin City',
            'is_active' => true,
            'logistics_company_id' => $this->originCompany->id,
        ]);
        $this->destinationHub = LogisticsCenter::create([
            'name' => 'Destination Hub',
            'code' => 'DST',
            'address' => '2 Destination Street',
            'city' => 'Destination City',
            'is_active' => true,
            'logistics_company_id' => $this->destinationCompany->id,
        ]);
        $this->foreignHub = LogisticsCenter::create([
            'name' => 'Foreign Hub',
            'code' => 'FRN',
            'address' => '3 Foreign Street',
            'city' => 'Foreign City',
            'is_active' => true,
            'logistics_company_id' => $this->foreignCompany->id,
        ]);

        ServiceArea::create([
            'name' => 'Origin Area',
            'logistics_center_id' => $this->originHub->id,
            'is_active' => true,
        ]);
        ServiceArea::create([
            'name' => 'Destination Area',
            'logistics_center_id' => $this->destinationHub->id,
            'is_active' => true,
        ]);

        $this->globalAdmin = new User([
            'name' => 'Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'password' => Hash::make('secret'),
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->globalAdmin->id = User::GLOBAL_LOGISTICS_ADMIN_ID;
        $this->globalAdmin->save();

        $this->originStaff = $this->staff('Origin Staff Person', 'origin.staff@pd.test', $this->originCompany, $this->originHub);
        $this->destinationStaff = $this->staff('Destination Staff', 'destination.staff@pd.test', $this->destinationCompany, $this->destinationHub);
        $this->foreignStaff = $this->staff('Foreign Staff', 'foreign.staff@pd.test', $this->foreignCompany, $this->foreignHub);
    }

    private function seedTransfer(): void
    {
        $originCreator = $this->staff('Origin Creator Private', 'origin.creator@pd.test', $this->originCompany, $this->originHub);
        $originReviewer = $this->staff('Origin Reviewer Private', 'origin.reviewer@pd.test', $this->originCompany, $this->originHub);
        $originDispatcher = $this->staff('Origin Hub Dispatcher', 'origin.dispatcher@pd.test', $this->originCompany, $this->originHub);
        $sellerUser = $this->staff('Seller Store Owner', 'seller.store@pd.test', $this->originCompany, $this->originHub);
        $buyer = $this->staff('Buyer Account', 'buyer.account@pd.test', $this->foreignCompany, $this->foreignHub);

        Seller::create([
            'user_id' => $sellerUser->id,
            'business_name' => 'Origin Marketplace Store',
            'phone' => '09170000001',
            'status' => 'approved',
            'address_line' => '1 Origin Street',
            'barangay' => 'Barangay Origin',
            'municipality' => 'Origin City',
            'province' => 'Origin Province',
        ]);

        $order = Order::create([
            'seller_id' => $sellerUser->id,
            'buyer_id' => $buyer->id,
            'status' => 'shipped',
            'payment_status' => 'paid',
            'total' => 550,
            'total_amount' => 550,
            'shipping_fee' => 50,
            'notes' => null,
            'shipping_address' => [
                'recipient_name' => 'Cross Company Recipient',
                'phone' => '09170000002',
                'address_line' => '2 Destination Street',
                'city' => 'Destination City',
                'province' => 'Destination Province',
            ],
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'seller_id' => $sellerUser->id,
            'product_name' => 'Parcel Contents',
            'quantity' => 1,
            'price' => 500,
        ]);

        DB::table('payments')->insert([
            'order_id' => $order->id,
            'method' => 'gcash',
            'amount' => 550,
            'status' => 'paid',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $interHubRider = $this->rider('Inter Hub Rider Private', $this->originHub);
        $handoffRider = $this->rider('Handoff Rider Private', $this->originHub);
        $pickupRider = $this->rider('Pickup Rider Private', $this->originHub);

        $this->delivery = Delivery::create([
            'tracking_number' => 'TRK-PRIV-0001',
            'order_id' => $order->id,
            'seller_id' => $sellerUser->id,
            'rider_id' => null,
            'center_id' => $this->originHub->id,
            'origin_center_id' => $this->originHub->id,
            'destination_center_id' => $this->destinationHub->id,
            'service_area_id' => ServiceArea::where('name', 'Destination Area')->value('id'),
            'status' => 'waiting_for_rider',
            'parcel_status' => 'dispatched',
            'sender_name' => 'Cross Company Sender',
            'sender_address' => '1 Origin Street',
            'recipient_name' => 'Cross Company Recipient',
            'recipient_address' => '2 Destination Street, Destination City',
            'payment_method' => 'gcash',
            'delivery_fee' => 50,
            'weight' => 2.5,
            'package_type' => 'Box',
            'created_by' => $originCreator->id,
            'received_at' => now()->subHours(6),
            'scanned_at' => now()->subHours(5),
            'sorted_at' => now()->subHours(4),
            'dispatched_at' => now()->subHours(3),
            'sorting_center_handoff_at' => now()->subDay(),
            'sorting_center_handoff_rider_id' => $handoffRider->id,
            'sorting_center_pickup_at' => now()->subHours(20),
            'sorting_center_pickup_rider_id' => $pickupRider->id,
            'inter_hub_rider_id' => $interHubRider->id,
            'inter_hub_dispatched_at' => now()->subHours(3),
        ]);

        DeliveryItem::create([
            'delivery_id' => $this->delivery->id,
            'name' => 'Parcel Contents',
            'quantity' => 1,
            'price' => 500,
        ]);

        PickupRequest::create([
            'delivery_id' => $this->delivery->id,
            'center_id' => $this->originHub->id,
            'status' => 'approved',
            'requested_at' => now()->subDays(2),
            'reviewed_at' => now()->subDays(2)->addHour(),
            'reviewed_by' => $originReviewer->id,
        ]);

        DeliveryStatusLog::create([
            'delivery_id' => $this->delivery->id,
            'status' => 'sorting_center_handoff',
            'location' => 'Origin Hub',
            'notes' => 'Handed off by Handoff Rider Private to Origin Hub.',
            'changed_by' => $originDispatcher->id,
        ]);
        DeliveryStatusLog::create([
            'delivery_id' => $this->delivery->id,
            'status' => 'received',
            'location' => 'Destination Hub',
            'notes' => 'Parcel received at Destination Hub receiving bay.',
            'changed_by' => $this->destinationStaff->id,
        ]);
        DeliveryStatusLog::create([
            'delivery_id' => $this->delivery->id,
            'status' => 'assigned',
            'location' => 'Origin Hub',
            'notes' => 'Assigned to rider Origin Assign Rider (Motorcycle, plate ASSIGN-PLATE-7711).',
            'changed_by' => $originDispatcher->id,
        ]);
        DeliveryStatusLog::create([
            'delivery_id' => $this->delivery->id,
            'status' => 'waiting_for_rider',
            'location' => 'Origin Hub',
            'notes' => 'Unassigned from suspended rider Origin Suspended Rider. Reason: policy violation.',
            'changed_by' => $originDispatcher->id,
        ]);

        $this->transfer = HubTransfer::create([
            'reference' => 'HT-PRIV-1',
            'logistics_company_id' => $this->originCompany->id,
            'origin_center_id' => $this->originHub->id,
            'destination_center_id' => $this->destinationHub->id,
            'truck_id' => null,
            'driver_id' => null,
            'status' => 'arrived',
            'created_by' => $originDispatcher->id,
            'planned_dispatch_at' => now()->subHours(3),
            'dispatched_at' => now()->subHours(3),
            'arrived_at' => now()->subHour(),
            'destination_arrived_at' => now()->subHour(),
        ]);

        HubTransferParcel::create([
            'hub_transfer_id' => $this->transfer->id,
            'delivery_id' => $this->delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'pending',
            'condition' => null,
        ]);

        $events = [
            ['truck_assigned', 'Assigned ORIGIN-PLATE-9911 to this leg.', 'Origin Staff Person', 'Origin Hub Dispatcher'],
            ['driver_assigned', 'Assigned Origin Driver Private to this leg.', 'Origin Staff Person', 'Origin Hub Dispatcher'],
            ['dispatched', 'Loaded by Origin Staff Person under ORIGIN-PLATE-9911.', 'Origin Staff Person', 'Origin Hub Dispatcher'],
            ['arrived', 'Arrived at Destination Hub under Origin Driver Private.', 'Origin Driver Private', 'Origin Driver Private'],
            ['truck_reassigned', 'Reassigned to ORIGIN-PLATE-9922 after inspection.', 'Origin Staff Person', 'Origin Hub Dispatcher'],
            ['driver_reassigned', 'Reassigned to Origin Driver Private after inspection.', 'Origin Staff Person', 'Origin Hub Dispatcher'],
            ['parcel_received', 'Condition: good', 'Destination Receiver', 'Destination Receiver'],
        ];

        foreach ($events as [$type, $notes, $actor, $dispatchActor]) {
            HubTransferEvent::create([
                'hub_transfer_id' => $this->transfer->id,
                'delivery_id' => $this->delivery->id,
                'event_type' => $type,
                'previous_status' => in_array($type, ['truck_reassigned', 'driver_reassigned'], true) ? 'assigned' : null,
                'new_status' => 'arrived',
                'notes' => $notes,
                'actor_name' => $dispatchActor === 'Destination Receiver' ? 'Destination Receiver' : $actor,
                'hub_transport_driver_id' => null,
            ]);
        }
    }

    private function rider(string $name, LogisticsCenter $center): Rider
    {
        return Rider::create([
            'user_id' => $this->staff($name, strtolower(str_replace(' ', '.', $name)).'@pd.test', $center->company, $center)->id,
            'center_id' => $center->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@pd.test',
            'vehicle_type' => 'motorcycle',
            'vehicle_capacity_kg' => 100,
            'license_plate' => 'RIDER-PLATE-1',
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'status' => 'active',
            'is_verified' => true,
            'is_online' => true,
            'approved_at' => now(),
        ]);
    }

    private function staff(string $name, string $email, LogisticsCompany $company, LogisticsCenter $center): User
    {
        return User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => 'staff',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
            'center_id' => $center->id,
        ]);
    }

    /**
     * Received-at-destination parcel carrying origin assignment notes and
     * destination custody notes, shared by the alternate-surface tests.
     */
    private function receivedParcelWithCrossCompanyLogs(string $tracking): Delivery
    {
        $delivery = $this->standaloneParcelReceivedAtDestination($tracking, $this->originHub->id, 'Origin Assignment Writer');

        $this->statusLogAt($delivery, now()->subMinutes(20)->seconds(0), [
            'status' => 'assigned',
            'location' => 'Origin Assign Desk',
            'notes' => 'Assigned to rider Origin Assign Rider (Motorcycle, plate ASSIGN-PLATE-7711).',
            'changed_by' => $this->originStaff->id,
        ]);
        $this->statusLogAt($delivery, now()->subMinutes(15)->seconds(0), [
            'status' => 'waiting_for_rider',
            'location' => 'Origin Hub',
            'notes' => 'Unassigned from suspended rider Origin Suspended Rider. Reason: policy violation.',
            'changed_by' => $this->originStaff->id,
        ]);
        $this->statusLogAt($delivery, now()->subMinutes(5)->seconds(0), [
            'status' => 'received',
            'location' => 'Destination Hub Receiving',
            'notes' => 'Destination receiving inspection — custody accepted.',
            'changed_by' => $this->destinationStaff->id,
        ]);
        $this->statusLogAt($delivery, now()->subMinutes(2)->seconds(0), [
            'status' => 'picked_up',
            'location' => 'Destination Hub',
            'notes' => 'Destination rider collected the parcel.',
            'changed_by' => null,
        ]);

        return $delivery;
    }

    /**
     * status-log timestamps are not mass assignable, so pin them explicitly.
     */
    private function statusLogAt(Delivery $delivery, \Illuminate\Support\Carbon $at, array $attributes): DeliveryStatusLog
    {
        $log = DeliveryStatusLog::create(['delivery_id' => $delivery->id] + $attributes);
        $log->forceFill(['created_at' => $at, 'updated_at' => $at])->save();

        return $log;
    }

    /** @return array{0: User, 1: Rider} */
    private function riderAccount(string $name, string $email, LogisticsCenter $center): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => 'rider',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => null,
            'center_id' => null,
        ]);

        $rider = Rider::create([
            'user_id' => $user->id,
            'center_id' => $center->id,
            'name' => $name,
            'email' => $email,
            'vehicle_type' => 'motorcycle',
            'vehicle_capacity_kg' => 100,
            'license_plate' => 'RIDER-PLATE-2',
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'status' => 'active',
            'is_verified' => true,
            'is_online' => true,
            'approved_at' => now(),
        ]);

        return [$user, $rider];
    }

    private function createSchema(): void
    {
        $schema = Schema::connection('sqlite');

        foreach ([
            'hub_transfer_events', 'hub_transfer_parcels', 'hub_transfers',
            'delivery_failures', 'delivery_proofs', 'delivery_items', 'delivery_status_logs', 'deliveries',
            'order_items', 'payments', 'order_vouchers', 'orders', 'addresses',
            'pickup_requests', 'transactions', 'notifications', 'service_areas', 'rider_locations',
            'riders', 'vehicle_types', 'sellers', 'logistics_settings',
            'hub_transport_drivers', 'logistics_trucks', 'logistics_centers',
            'logistics_companies', 'users',
        ] as $table) {
            $schema->dropIfExists($table);
        }

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('rider');
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

        $schema->create('logistics_trucks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('hub_transport_driver_id')->nullable();
            $table->string('ownership_type')->nullable();
            $table->string('plate_number')->nullable();
            $table->string('name')->nullable();
            $table->string('model')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('status')->default('available');
            $table->string('verification_status')->default('approved');
            $table->integer('parcel_capacity')->default(0);
            $table->decimal('capacity_kg', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('hub_transport_drivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('logistics_truck_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('status')->default('available');
            $table->string('license_number')->nullable();
            $table->date('license_expiry')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('label')->nullable();
            $table->decimal('capacity_kg', 8, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $schema->create('riders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
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
            $table->string('vehicle_registration')->nullable();
            $table->string('status')->default('active');
            $table->string('vehicle_verification')->nullable();
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        $schema->create('rider_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('rider_id');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('recorded_at')->nullable();
            $table->timestamps();
        });

        $schema->create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('logistics_center_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('store_name')->nullable();
            $table->string('business_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('approved');
            $table->string('approval_status')->nullable();
            $table->string('address_line')->nullable();
            $table->string('barangay')->nullable();
            $table->string('municipality')->nullable();
            $table->string('province')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('addresses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address_line')->nullable();
            $table->string('barangay')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->timestamps();
        });

        $schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->unsignedBigInteger('address_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('pending');
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('sub_total', 12, 2)->default(0);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_fee', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->text('shipping_address')->nullable();
            $table->string('delivery_status')->nullable();
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('product_name')->nullable();
            $table->string('variant_label')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->decimal('subtotal', 12, 2)->nullable();
            $table->decimal('total_price', 12, 2)->nullable();
            $table->timestamps();
        });

        $schema->create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->string('method')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('pending');
            $table->timestamps();
        });

        $schema->create('order_vouchers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->timestamps();
        });

        $schema->create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id')->nullable();
            $table->string('tracking_number')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->unsignedBigInteger('logistics_center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->unsignedBigInteger('origin_center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('pickup_rider_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('rider_fee', 12, 2)->default(0);
            $table->decimal('logistics_fee', 12, 2)->default(0);
            $table->decimal('admin_commission', 12, 2)->default(0);
            $table->string('status')->default('pending');
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
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('archived_by')->nullable();
            $table->string('status')->default('waiting_for_rider');
            $table->string('parcel_status')->nullable();
            $table->string('priority')->default('normal');
            $table->string('package_type')->nullable();
            $table->string('package_description')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->text('sender_address')->nullable();
            $table->decimal('sender_lat', 10, 7)->nullable();
            $table->decimal('sender_lng', 10, 7)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address')->nullable();
            $table->decimal('recipient_lat', 10, 7)->nullable();
            $table->decimal('recipient_lng', 10, 7)->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('amount_to_collect', 12, 2)->nullable();
            $table->decimal('cod_amount_received', 12, 2)->nullable();
            $table->decimal('delivery_fee', 12, 2)->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('pickup_pin')->nullable();
            $table->text('notes')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->timestamp('estimated_delivery_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('sorting_center_handoff_at')->nullable();
            $table->unsignedBigInteger('sorting_center_handoff_rider_id')->nullable();
            $table->timestamp('sorting_center_pickup_at')->nullable();
            $table->unsignedBigInteger('sorting_center_pickup_rider_id')->nullable();
            $table->unsignedBigInteger('inter_hub_rider_id')->nullable();
            $table->timestamp('inter_hub_dispatched_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('return_reason')->nullable();
            $table->timestamp('reattempt_requested_at')->nullable();
            $table->timestamp('cod_received_at')->nullable();
            $table->text('delivery_otp')->nullable();
            $table->timestamp('delivery_otp_expires_at')->nullable();
            $table->unsignedTinyInteger('delivery_otp_attempts')->default(0);
            $table->timestamp('delivery_otp_consumed_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('archive_note')->nullable();
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

        $schema->create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('name')->nullable();
            $table->string('variant_label')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 12, 2)->default(0);
            $table->timestamps();
        });

        $schema->create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->string('type')->nullable();
            $table->string('file_path')->nullable();
            $table->string('signature_name')->nullable();
            $table->string('otp')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->unsignedBigInteger('rider_id');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamp('reported_at')->nullable();
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
            $table->text('rejection_reason')->nullable();
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

        $schema->create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('icon')->nullable();
            $table->string('priority')->nullable();
            $table->boolean('is_read')->default(false);
            $table->string('link')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->string('audience')->nullable();
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
