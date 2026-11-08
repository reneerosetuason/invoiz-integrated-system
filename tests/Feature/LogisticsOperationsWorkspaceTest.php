<?php

namespace Tests\Feature;

use App\Http\Controllers\Logistics\OperationsController;
use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
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

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

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
            'delivery_status_logs', 'deliveries', 'pickup_requests', 'logistics_trucks',
            'hub_transport_drivers', 'riders', 'logistics_centers',
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

        $schema->create('riders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_online')->default(false);
            $table->boolean('is_verified')->default(true);
            $table->timestamp('approved_at')->nullable();
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
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('failed_at')->nullable();
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
