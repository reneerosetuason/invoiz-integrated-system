<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PickupRequest;
use App\Models\Rider;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\FulfillmentWorkflow;
use App\Services\HubTransferService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * First-mile receiving contract for the Origin Hub.
 *
 * A Delivery Rider collects the seller parcel, records the hub handoff, and
 * Origin Hub staff accept that single handover before scanning and sorting.
 * The Hub Transfer receiving surface (manifest condition choices) is a
 * separate workflow and must stay untouched.
 *
 * Runs on a self-contained in-memory SQLite schema so no MySQL instance is
 * touched and no migrator runs.
 */
class OriginHubRiderReceivingTest extends TestCase
{
    private LogisticsCompany $company;
    private LogisticsCenter $origin;
    private LogisticsCenter $destination;
    private ServiceArea $originArea;
    private ServiceArea $destinationArea;
    private User $staff;
    private User $riderUser;
    private Rider $rider;

    private static int $trackingSequence = 0;

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
    // 1. Rider pickup records first-mile custody
    // ------------------------------------------------------------------

    public function test_rider_pickup_records_first_mile_custody(): void
    {
        $delivery = $this->orderDelivery(status: 'arrived_at_shop');
        $this->actAsRider();

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/pickup")
            ->assertOk();

        $delivery->refresh();
        $this->assertSame('picked_up', $delivery->status);
        $this->assertNotNull($delivery->picked_up_at);
        $this->assertNull($delivery->sorting_center_handoff_at);
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'picked_up',
        ]);
        $this->assertSame('shipped', $delivery->order->fresh()->status);
    }

    // ------------------------------------------------------------------
    // 2. Rider hands the parcel to the correct Origin Hub
    // ------------------------------------------------------------------

    public function test_rider_hands_parcel_to_the_correct_origin_hub(): void
    {
        $delivery = $this->orderDelivery(status: 'arrived_at_shop');
        $this->actAsRider();

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/pickup")->assertOk();
        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/sorting-center-handoff")
            ->assertOk();

        $delivery->refresh();
        $this->assertSame($this->origin->id, (int) $delivery->center_id);
        $this->assertNotNull($delivery->sorting_center_handoff_at);
        $this->assertSame($this->rider->id, (int) $delivery->sorting_center_handoff_rider_id);

        $handoff = DB::table('delivery_status_logs')
            ->where('delivery_id', $delivery->id)
            ->where('status', 'sorting_center_handoff')
            ->first();

        $this->assertNotNull($handoff, 'The rider handoff must be recorded in tracking history.');
        $this->assertSame($this->riderUser->id, (int) $handoff->changed_by);
        $this->assertStringContainsString($this->rider->name, (string) $handoff->notes);
    }

    // ------------------------------------------------------------------
    // 3. Staff see only the handover verification for a verified handoff
    // ------------------------------------------------------------------

    public function test_origin_hub_shows_only_handover_verification_for_verified_handoffs(): void
    {
        $handedOff = $this->orderDelivery(
            status: 'picked_up',
            parcelStatus: 'pending_arrival',
            withHandoff: true,
        );

        $this->actingAs($this->staff);
        $response = $this->get(route('sorting-center.index'));
        $response->assertOk();
        $response->assertSee('Accept handover');
        $response->assertDontSee('Receive at center');
        $this->assertSame('pending_arrival', $handedOff->fresh()->parcel_status);

        // A standalone direct intake has no rider handoff to accept, so the
        // explicit hub selection must remain available for it.
        $this->standaloneDelivery(status: 'waiting_for_rider', parcelStatus: 'pending_arrival');

        $second = $this->get(route('sorting-center.index'));
        $second->assertOk();
        $second->assertSee('Receive at center');
    }

    // ------------------------------------------------------------------
    // 4. Successful handover enables receive -> scan -> sort
    // ------------------------------------------------------------------

    public function test_successful_handover_enables_receive_scan_and_sort(): void
    {
        $delivery = $this->orderDelivery(
            status: 'picked_up',
            parcelStatus: 'pending_arrival',
            withHandoff: true,
        );

        $this->actingAs($this->staff);

        $this->post(route('deliveries.receive', $delivery))
            ->assertSessionHas('success', 'Parcel received and logged.');

        $delivery->refresh();
        $this->assertSame('received', $delivery->parcel_status);
        $this->assertNotNull($delivery->received_at);
        $this->assertSame($this->origin->id, (int) $delivery->center_id);
        $this->assertSame('waiting_for_rider', $delivery->status);
        $this->assertNull($delivery->rider_id);
        $this->assertDatabaseHas('pickup_requests', [
            'delivery_id' => $delivery->id,
            'status' => 'completed',
        ]);

        $this->patch(route('deliveries.scan', $delivery))
            ->assertSessionHas('success', 'Parcel scanned successfully.');
        $this->assertSame('scanned', $delivery->fresh()->parcel_status);

        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $this->destination->id,
            'service_area_id' => $this->destinationArea->id,
        ])->assertSessionHas('success', 'Parcel sorted successfully.');

        $delivery->refresh();
        $this->assertSame('sorted', $delivery->parcel_status);
        $this->assertSame($this->destination->id, (int) $delivery->destination_center_id);
        $this->assertSame($this->destinationArea->id, (int) $delivery->service_area_id);
        // Hub receipt and sorting never mark the buyer order delivered.
        $this->assertSame('ready_for_delivery', $delivery->order->fresh()->status);
    }

    // ------------------------------------------------------------------
    // 5. Unverified handover blocks receive, scan and sort
    // ------------------------------------------------------------------

    public function test_unverified_handover_blocks_receive_scan_and_sort(): void
    {
        $delivery = $this->orderDelivery(
            status: 'picked_up',
            parcelStatus: 'pending_arrival',
            withHandoff: false,
            pickedUp: true,
        );

        $this->actingAs($this->staff);
        $logsBefore = DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count();

        $this->post(route('deliveries.receive', $delivery), ['center_id' => $this->origin->id])
            ->assertSessionHas('error');
        $this->assertSame('pending_arrival', $delivery->fresh()->parcel_status);

        $this->patch(route('deliveries.scan', $delivery))->assertSessionHas('error');

        // Even a parcel that somehow reached "scanned" cannot be sorted without
        // recorded custody.
        $delivery->update(['parcel_status' => 'scanned', 'scanned_at' => now()]);
        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $this->destination->id,
            'service_area_id' => $this->destinationArea->id,
        ])->assertSessionHas('error');

        $delivery->refresh();
        $this->assertSame('scanned', $delivery->parcel_status);
        $this->assertNull($delivery->destination_center_id);
        $this->assertSame(
            $logsBefore,
            DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count(),
            'Blocked receiving must not write parcel tracking events.'
        );
        $this->assertDatabaseMissing('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'received',
        ]);

        // The custody service itself refuses an unverified receipt.
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        FulfillmentWorkflow::receive($delivery, $this->staff->id);
    }

    // ------------------------------------------------------------------
    // 6. Hub Transfer arrivals keep their original receiving choices
    // ------------------------------------------------------------------

    public function test_hub_transfer_arrivals_keep_their_existing_receiving_choices(): void
    {
        $view = (string) file_get_contents(base_path('resources/views/logistics/hub-transfers/show.blade.php'));

        $this->assertStringContainsString("route('hub-transfers.receive'", $view);
        $this->assertStringContainsString('name="condition"', $view);
        $this->assertStringContainsString('value="good"', $view);
        $this->assertStringContainsString('value="damaged"', $view);
        $this->assertStringContainsString('value="missing"', $view);
        $this->assertStringContainsString('name="exception_reason"', $view);
        $this->assertStringContainsString('Finalize Receiving', $view);
        $this->assertStringContainsString('Confirm Arrival', $view);
        $this->assertStringContainsString('Confirm Dispatch', $view);
    }

    // ------------------------------------------------------------------
    // 7. Intermediate hub still receives parcels through the manifest
    // ------------------------------------------------------------------

    public function test_intermediate_hub_receives_transferred_parcel_via_manifest(): void
    {
        $delivery = $this->standaloneDelivery(status: 'waiting_for_rider', parcelStatus: 'dispatched', center: $this->origin);
        $delivery->update(['destination_center_id' => $this->destination->id]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-TEST-0001',
            'logistics_company_id' => $this->company->id,
            'origin_center_id' => $this->origin->id,
            'destination_center_id' => $this->destination->id,
            'status' => 'arrived',
        ]);

        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'pending',
        ]);

        $result = HubTransferService::receiveParcel($transfer, $delivery->id, 'good', null, $this->staff);

        $this->assertFalse($result['duplicate']);
        $this->assertSame('received', $result['entry']->receiving_status);
        $this->assertSame('good', $result['entry']->condition);

        $delivery->refresh();
        $this->assertSame($this->destination->id, (int) $delivery->center_id, 'Receipt moves the parcel to the destination hub.');
        $this->assertSame('received', $delivery->parcel_status);
        $this->assertNotNull($delivery->received_at);

        $log = DB::table('delivery_status_logs')
            ->where('delivery_id', $delivery->id)
            ->where('status', 'received')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame($this->destination->name, $log->location);
        $this->assertSame($this->staff->id, (int) $log->changed_by);
        $this->assertDatabaseHas('hub_transfer_events', [
            'hub_transfer_id' => $transfer->id,
            'event_type' => 'parcel_received',
        ]);

        $logCount = DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count();
        $eventCount = DB::table('hub_transfer_events')->where('hub_transfer_id', $transfer->id)->count();

        $duplicate = HubTransferService::receiveParcel($transfer->fresh(), $delivery->id, 'good', null, $this->staff);

        $this->assertTrue($duplicate['duplicate']);
        $this->assertSame($logCount, DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count());
        $this->assertSame($eventCount, DB::table('hub_transfer_events')->where('hub_transfer_id', $transfer->id)->count());
    }

    // ------------------------------------------------------------------
    // 8. Tracking history records rider, staff, facility and events
    // ------------------------------------------------------------------

    public function test_tracking_history_records_rider_staff_and_facility(): void
    {
        $delivery = $this->orderDelivery(status: 'arrived_at_shop');
        $this->actAsRider();
        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/pickup")->assertOk();
        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/sorting-center-handoff")->assertOk();

        $this->actingAs($this->staff);
        $this->post(route('deliveries.receive', $delivery))->assertSessionHas('success');
        $this->patch(route('deliveries.scan', $delivery))->assertSessionHas('success');
        $this->post(route('deliveries.sort', $delivery), [
            'destination_center_id' => $this->destination->id,
            'service_area_id' => $this->destinationArea->id,
        ])->assertSessionHas('success');

        $delivery->refresh();
        $this->assertSame($this->origin->id, (int) $delivery->center_id);
        $this->assertSame($this->rider->id, (int) $delivery->sorting_center_handoff_rider_id);

        $handoff = DB::table('delivery_status_logs')
            ->where('delivery_id', $delivery->id)
            ->where('status', 'sorting_center_handoff')
            ->first();
        $this->assertSame($this->riderUser->id, (int) $handoff->changed_by);
        $this->assertStringContainsString($this->rider->name, (string) $handoff->notes);

        foreach (['received', 'scanned', 'sorted'] as $status) {
            $log = DB::table('delivery_status_logs')
                ->where('delivery_id', $delivery->id)
                ->where('status', $status)
                ->first();
            $this->assertNotNull($log, "Tracking history is missing the {$status} event.");
            $this->assertSame($this->staff->id, (int) $log->changed_by, "The {$status} event must keep the staff identity.");
        }

        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $delivery->order_id,
            'to_status' => 'shipped',
        ]);
    }

    // ------------------------------------------------------------------
    // 9. Repeated handoff/receive never duplicates tracking events
    // ------------------------------------------------------------------

    public function test_repeated_handoff_and_receive_do_not_duplicate_events(): void
    {
        $delivery = $this->orderDelivery(status: 'arrived_at_shop');
        $this->actAsRider();
        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/pickup")->assertOk();

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/sorting-center-handoff")->assertOk();
        $afterFirstHandoff = DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count();

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/sorting-center-handoff")
            ->assertOk()
            ->assertJsonPath('message', 'Center handoff already recorded.');
        $this->assertSame(
            $afterFirstHandoff,
            DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count(),
            'A repeated handoff must not append another event.'
        );

        $this->actingAs($this->staff);
        $this->post(route('deliveries.receive', $delivery))->assertSessionHas('success');
        $afterFirstReceive = DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count();
        $this->assertSame(1, DB::table('delivery_status_logs')
            ->where('delivery_id', $delivery->id)
            ->where('status', 'received')
            ->count());

        $this->post(route('deliveries.receive', $delivery))->assertSessionHas('error');
        $this->assertSame($afterFirstReceive, DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count());
        $this->assertSame(1, DB::table('delivery_status_logs')
            ->where('delivery_id', $delivery->id)
            ->where('status', 'received')
            ->count());
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    private function seedFacilities(): void
    {
        $this->company = LogisticsCompany::create(['name' => 'INVOIZ Logistics', 'status' => 'active']);

        $this->origin = LogisticsCenter::create([
            'name' => 'Origin Hub',
            'address' => '1 Origin Street',
            'is_active' => true,
            'logistics_company_id' => $this->company->id,
        ]);
        $this->destination = LogisticsCenter::create([
            'name' => 'Destination Hub',
            'address' => '2 Destination Street',
            'is_active' => true,
            'logistics_company_id' => $this->company->id,
        ]);

        $this->originArea = ServiceArea::create([
            'name' => 'Origin Area',
            'logistics_center_id' => $this->origin->id,
            'is_active' => true,
        ]);
        $this->destinationArea = ServiceArea::create([
            'name' => 'Destination Area',
            'logistics_center_id' => $this->destination->id,
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'name' => 'Hub Staff',
            'first_name' => 'Hub',
            'last_name' => 'Staff',
            'email' => 'origin.staff@example.test',
            'password' => Hash::make('secret'),
            'role' => 'staff',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'center_id' => $this->origin->id,
            'logistics_company_id' => $this->company->id,
        ]);

        $this->riderUser = User::create([
            'name' => 'Pickup Rider',
            'first_name' => 'Pickup',
            'last_name' => 'Rider',
            'email' => 'pickup.rider@example.test',
            'password' => Hash::make('secret'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->rider = Rider::create([
            'user_id' => $this->riderUser->id,
            'center_id' => $this->origin->id,
            'service_area_id' => $this->originArea->id,
            'name' => $this->riderUser->name,
            'email' => $this->riderUser->email,
            'vehicle_type' => 'motorcycle',
            'vehicle_verification' => 'verified',
            'status' => 'available',
            'is_verified' => true,
            'approved_at' => now(),
        ]);
    }

    private function actAsRider(): void
    {
        Sanctum::actingAs($this->riderUser, ['rider']);
    }

    private function orderDelivery(
        string $status,
        string $parcelStatus = 'pending_arrival',
        bool $withHandoff = false,
        bool $pickedUp = false,
    ): Delivery {
        $order = Order::create([
            'buyer_id' => 900,
            'seller_id' => 901,
            'status' => 'ready_for_delivery',
            'payment_status' => 'unpaid',
            'total_amount' => 300,
            'total' => 300,
        ]);

        Payment::create([
            'order_id' => $order->id,
            'method' => 'cod',
            'status' => 'pending',
            'amount' => 300,
        ]);

        $delivery = Delivery::create([
            'tracking_number' => $this->nextTracking(),
            'order_id' => $order->id,
            'seller_id' => 901,
            'rider_id' => $this->rider->id,
            'center_id' => $this->origin->id,
            'service_area_id' => $this->originArea->id,
            'status' => $status,
            'parcel_status' => $parcelStatus,
            'sender_name' => 'INVOIZ Seller',
            'sender_address' => '10 Seller Street',
            'recipient_name' => 'Buyer',
            'recipient_address' => '20 Buyer Street',
            'payment_method' => 'cod',
            'amount_to_collect' => 300,
            'delivery_fee' => 50,
            'picked_up_at' => $withHandoff || $pickedUp ? now() : null,
            'sorting_center_handoff_at' => $withHandoff ? now() : null,
            'sorting_center_handoff_rider_id' => $withHandoff ? $this->rider->id : null,
        ]);

        PickupRequest::create([
            'delivery_id' => $delivery->id,
            'center_id' => $this->origin->id,
            'status' => 'approved',
            'requested_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $this->staff->id,
        ]);

        return $delivery;
    }

    private function standaloneDelivery(
        string $status,
        string $parcelStatus,
        ?LogisticsCenter $center = null,
    ): Delivery {
        return Delivery::create([
            'tracking_number' => $this->nextTracking(),
            'center_id' => ($center ?? $this->origin)->id,
            'service_area_id' => $this->originArea->id,
            'status' => $status,
            'parcel_status' => $parcelStatus,
            'sender_name' => 'Walk-in Sender',
            'sender_address' => '30 Sender Street',
            'recipient_name' => 'Recipient',
            'recipient_address' => '40 Recipient Street',
            'delivery_fee' => 50,
        ]);
    }

    private function nextTracking(): string
    {
        self::$trackingSequence++;

        return 'TRK-20261107-' . str_pad((string) self::$trackingSequence, 4, 'A', STR_PAD_LEFT);
    }

    private function createSchema(): void
    {
        $schema = Schema::connection('sqlite');

        foreach ([
            'hub_transfer_events', 'hub_transfer_parcels', 'hub_transfers',
            'order_status_histories', 'courier_pickups', 'seller_notifications',
            'delivery_failures', 'delivery_proofs', 'delivery_status_logs',
            'delivery_items', 'pickup_requests', 'payments', 'deliveries',
            'orders', 'sellers', 'logistics_settings', 'riders', 'vehicle_types',
            'service_areas', 'logistics_centers', 'logistics_companies', 'users',
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

        $schema->create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('logistics_center_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
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
            $table->string('vehicle_verification')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        $schema->create('sellers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('business_name')->nullable();
            $table->string('status')->nullable();
            $table->string('approval_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->nullable();
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('status')->default('pending');
            $table->string('payment_status')->default('unpaid');
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('method')->nullable();
            $table->string('status')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('proof_path')->nullable();
            $table->timestamp('paid_at')->nullable();
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
            $table->decimal('sender_lat', 10, 7)->nullable();
            $table->decimal('sender_lng', 10, 7)->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->string('recipient_address')->nullable();
            $table->decimal('recipient_lat', 10, 7)->nullable();
            $table->decimal('recipient_lng', 10, 7)->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('amount_to_collect', 10, 2)->nullable();
            $table->decimal('cod_amount_received', 10, 2)->nullable();
            $table->timestamp('cod_received_at')->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();
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
            $table->string('failure_reason')->nullable();
            $table->timestamp('reattempt_requested_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('return_reason')->nullable();
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
            $table->unsignedBigInteger('inter_hub_rider_id')->nullable();
            $table->timestamp('inter_hub_dispatched_at')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('name')->nullable();
            $table->string('variant_label')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('subtotal', 10, 2)->default(0);
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

        $schema->create('delivery_proofs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('type')->nullable();
            $table->string('signature_name')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_failures', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('reason')->nullable();
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

        $schema->create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('from_status')->nullable();
            $table->string('to_status')->nullable();
            $table->text('notes')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamps();
        });

        $schema->create('courier_pickups', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->string('courier')->nullable();
            $table->timestamp('pickup_at')->nullable();
            $table->string('tracking_number')->nullable();
            $table->string('status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        $schema->create('seller_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('type')->nullable();
            $table->string('title')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });

        $schema->create('logistics_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('photo_path')->nullable();
            $table->text('preferences')->nullable();
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
