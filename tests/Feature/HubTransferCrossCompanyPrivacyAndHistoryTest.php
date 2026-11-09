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
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Cross-company Hub Transfer privacy and historical-record access.
 *
 * Fleet plates, driver names and driver trip details belong to the origin
 * company. A destination company may see the manifest needed to receive the
 * parcels, but never the origin fleet. A transfer also remains readable after
 * either hub is deactivated, while every operational mutation stays blocked.
 *
 * Runs on a self-contained in-memory SQLite schema, so no MySQL instance is
 * touched and no migrator runs.
 */
class HubTransferCrossCompanyPrivacyAndHistoryTest extends TestCase
{
    private LogisticsCompany $originCompany;
    private LogisticsCompany $destinationCompany;
    private LogisticsCompany $foreignCompany;

    private LogisticsCenter $originHub;
    private LogisticsCenter $destinationHub;
    private LogisticsCenter $siblingHub;
    private LogisticsCenter $foreignHub;

    private User $globalAdmin;
    private User $originStaff;
    private User $destinationStaff;
    private User $siblingStaff;
    private User $foreignStaff;
    private User $foreignManager;

    private LogisticsTruck $originTruck;
    private HubTransportDriver $originDriver;
    private LogisticsTruck $destinationTruck;
    private HubTransportDriver $destinationDriver;

    private static int $sequence = 0;

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
    }

    // ------------------------------------------------------------------
    // Hub Transfers index privacy
    // ------------------------------------------------------------------

    public function test_foreign_destination_staff_do_not_see_origin_fleet_in_transfer_index(): void
    {
        [$transfer] = $this->makeTransfer('received');

        $this->actingAs($this->destinationStaff)
            ->get(route('hub-transfers.index'))
            ->assertOk()
            ->assertSee($transfer->reference)
            ->assertSee($this->originHub->name)
            ->assertDontSee($this->originTruck->plate_number)
            ->assertDontSee($this->originDriver->name)
            ->assertSee('Managed by origin company');
    }

    public function test_origin_staff_see_their_own_outgoing_fleet_in_transfer_index(): void
    {
        [$transfer] = $this->makeTransfer('received');

        $this->actingAs($this->originStaff)
            ->get(route('hub-transfers.index'))
            ->assertOk()
            ->assertSee($transfer->reference)
            ->assertSee($this->originTruck->plate_number)
            ->assertSee($this->originDriver->name)
            ->assertDontSee('Managed by origin company');
    }

    // ------------------------------------------------------------------
    // Operations dashboard privacy
    // ------------------------------------------------------------------

    public function test_foreign_destination_staff_do_not_see_origin_fleet_on_operations_dashboard(): void
    {
        [$transfer, $delivery] = $this->makeTransfer('arrived');

        // The parcel is physically at the destination hub, so the action
        // queue and the incoming-transfer panel both describe this leg.
        $delivery->update([
            'center_id' => $this->destinationHub->id,
            'parcel_status' => 'received',
            'status' => 'waiting_for_rider',
        ]);

        $this->actingAs($this->destinationStaff)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSee($transfer->reference)
            ->assertDontSee($this->originTruck->plate_number)
            ->assertDontSee($this->originDriver->name)
            ->assertSee('Fleet managed by origin company');
    }

    public function test_origin_staff_see_their_own_outgoing_fleet_on_operations_dashboard(): void
    {
        // A company-owned leg departing the viewer's own hub keeps its fleet
        // details: this is legitimate origin-side operating information.
        $this->makeTransfer('loading', $this->destinationHub, $this->originHub);

        $this->actingAs($this->destinationStaff)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertSee($this->destinationDriver->name)
            ->assertSee('Transport driver: '.$this->destinationDriver->name)
            ->assertDontSee('Fleet managed by origin company');
    }

    // ------------------------------------------------------------------
    // Historical visibility after hub deactivation
    // ------------------------------------------------------------------

    public function test_completed_transfer_stays_viewable_after_origin_hub_is_deactivated(): void
    {
        [$transfer] = $this->makeTransfer('received');
        $this->originHub->update(['is_active' => false]);

        $this->actingAs($this->originStaff)
            ->get(route('hub-transfers.show', $transfer))
            ->assertOk()
            ->assertSee($transfer->reference)
            ->assertSee('One or both hubs are inactive. This transfer is available for historical reference only.')
            ->assertDontSee('Transfer Actions &amp; Lifecycle', false);
    }

    public function test_completed_transfer_stays_viewable_after_destination_hub_is_deactivated(): void
    {
        [$transfer] = $this->makeTransfer('received');
        $this->destinationHub->update(['is_active' => false]);

        $this->actingAs($this->destinationStaff)
            ->get(route('hub-transfers.show', $transfer))
            ->assertOk()
            ->assertSee($transfer->reference)
            ->assertSee('One or both hubs are inactive. This transfer is available for historical reference only.');
    }

    public function test_global_oversight_keeps_read_only_historical_access(): void
    {
        [$transfer] = $this->makeTransfer('received');
        $this->originHub->update(['is_active' => false]);

        $this->actingAs($this->globalAdmin)
            ->get(route('hub-transfers.show', $transfer))
            ->assertOk()
            ->assertSee($transfer->reference)
            ->assertSee('One or both hubs are inactive. This transfer is available for historical reference only.')
            ->assertDontSee('Confirm Dispatch')
            ->assertDontSee('Finalize Receiving');
    }

    public function test_historical_transfer_cannot_be_operationally_modified(): void
    {
        [$transfer, $delivery, $entry] = $this->makeTransfer('loading');
        $this->originHub->update(['is_active' => false]);

        $this->actingAs($this->originStaff);
        $this->post(route('hub-transfers.load', $transfer), [
            'tracking_number' => $delivery->tracking_number,
        ])->assertStatus(422);
        $this->delete(route('hub-transfers.parcels.remove', [$transfer, $entry]), [
            'reason' => 'Attempt while inactive',
        ])->assertStatus(422);
        $this->post(route('hub-transfers.dispatch', $transfer))->assertStatus(422);
        $this->post(route('hub-transfers.hold', $transfer), ['reason' => 'Hub is inactive'])->assertStatus(422);
        $this->post(route('hub-transfers.resume', $transfer))->assertStatus(422);
        $this->post(route('hub-transfers.reassign', $transfer), [
            'truck_id' => $this->originTruck->id,
        ])->assertStatus(422);
        $this->post(route('hub-transfers.reroute', $transfer), [
            'destination_center_id' => $this->siblingHub->id,
            'reason' => 'Attempt while inactive',
        ])->assertStatus(422);
        $this->post(route('hub-transfers.cancel', $transfer), ['reason' => 'Attempt while inactive'])->assertStatus(422);

        $this->actingAs($this->destinationStaff);
        $this->post(route('hub-transfers.arrive', $transfer))->assertStatus(422);
        $this->post(route('hub-transfers.receive', $transfer), [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ])->assertStatus(422);
        $this->post(route('hub-transfers.complete', $transfer))->assertStatus(422);

        $this->assertSame('loading', $transfer->fresh()->status);
        $this->assertSame('pending', HubTransferParcel::where('hub_transfer_id', $transfer->id)->value('receiving_status'));
        $this->assertSame($this->originHub->id, (int) $delivery->fresh()->center_id);
    }

    public function test_unrelated_accounts_still_receive_not_found(): void
    {
        [$transfer] = $this->makeTransfer('received');

        foreach ([$this->foreignStaff, $this->foreignManager, $this->siblingStaff] as $account) {
            $this->actingAs($account)
                ->get(route('hub-transfers.show', $transfer))
                ->assertNotFound();
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return array{0: HubTransfer, 1: Delivery, 2: HubTransferParcel} */
    private function makeTransfer(
        string $status,
        ?LogisticsCenter $origin = null,
        ?LogisticsCenter $destination = null,
    ): array {
        $origin ??= $this->originHub;
        $destination ??= $origin->is($this->originHub) ? $this->destinationHub : $this->originHub;
        $company = LogisticsCompany::findOrFail($origin->logistics_company_id);
        [$truck, $driver] = $this->fleetFor($company);

        $transfer = HubTransfer::create([
            'reference' => 'HT-CC-'.(++self::$sequence),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => $status,
            'planned_dispatch_at' => now(),
            'dispatched_at' => in_array($status, ['arrived', 'received'], true) ? now() : null,
            'arrived_at' => in_array($status, ['arrived', 'received'], true) ? now() : null,
            'destination_arrived_at' => in_array($status, ['arrived', 'received'], true) ? now() : null,
        ]);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-CC-'.str_pad((string) self::$sequence, 4, '0', STR_PAD_LEFT),
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => $status === 'received' ? 'received' : 'received',
            'sender_name' => 'Cross Company Sender',
            'sender_address' => '1 Origin Street',
            'recipient_name' => 'Cross Company Recipient',
            'recipient_address' => '2 Destination Street',
            'delivery_fee' => 50,
            'weight' => 2.5,
        ]);

        $entry = HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => in_array($status, ['arrived', 'received'], true) ? 'loaded' : 'pending',
            'receiving_status' => $status === 'received' ? 'received' : 'pending',
            'condition' => $status === 'received' ? 'good' : null,
        ]);

        HubTransferEvent::create([
            'hub_transfer_id' => $transfer->id,
            'event_type' => 'created',
            'new_status' => $status,
            'actor_name' => 'Origin Staff Person',
            'notes' => 'Fleet '.$truck->plate_number.' with '.$driver->name,
        ]);

        return [$transfer, $delivery, $entry];
    }

    /** @return array{0: LogisticsTruck, 1: HubTransportDriver} */
    private function fleetFor(LogisticsCompany $company): array
    {
        return $company->is($this->originCompany)
            ? [$this->originTruck, $this->originDriver]
            : [$this->destinationTruck, $this->destinationDriver];
    }

    private function seedNetwork(): void
    {
        $this->originCompany = LogisticsCompany::create(['name' => 'Origin Logistics', 'status' => 'active']);
        $this->destinationCompany = LogisticsCompany::create(['name' => 'Destination Logistics', 'status' => 'active']);
        $this->foreignCompany = LogisticsCompany::create(['name' => 'Foreign Logistics', 'status' => 'active']);

        $this->originHub = LogisticsCenter::create([
            'name' => 'Origin Hub',
            'address' => '1 Origin Street',
            'is_active' => true,
            'logistics_company_id' => $this->originCompany->id,
        ]);
        $this->destinationHub = LogisticsCenter::create([
            'name' => 'Destination Hub',
            'address' => '2 Destination Street',
            'is_active' => true,
            'logistics_company_id' => $this->destinationCompany->id,
        ]);
        $this->siblingHub = LogisticsCenter::create([
            'name' => 'Sibling Destination Hub',
            'address' => '3 Sibling Street',
            'is_active' => true,
            'logistics_company_id' => $this->destinationCompany->id,
        ]);
        $this->foreignHub = LogisticsCenter::create([
            'name' => 'Foreign Hub',
            'address' => '4 Foreign Street',
            'is_active' => true,
            'logistics_company_id' => $this->foreignCompany->id,
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

        $this->originStaff = $this->staff('Origin Staff', 'origin.staff@cc.test', $this->originCompany, $this->originHub);
        $this->destinationStaff = $this->staff('Destination Staff', 'destination.staff@cc.test', $this->destinationCompany, $this->destinationHub);
        $this->siblingStaff = $this->staff('Sibling Staff', 'sibling.staff@cc.test', $this->destinationCompany, $this->siblingHub);
        $this->foreignStaff = $this->staff('Foreign Staff', 'foreign.staff@cc.test', $this->foreignCompany, $this->foreignHub);

        $this->foreignManager = User::create([
            'name' => 'Foreign Manager',
            'email' => 'foreign.manager@cc.test',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $this->foreignCompany->id,
        ]);

        $this->originTruck = LogisticsTruck::create([
            'logistics_company_id' => $this->originCompany->id,
            'ownership_type' => 'company',
            'plate_number' => 'ORIGIN-PLATE-9911',
            'vehicle_type' => 'light truck',
            'model' => 'Isuzu Forward',
            'parcel_capacity' => 100,
            'capacity_kg' => 1500,
            'status' => 'assigned',
            'verification_status' => 'approved',
        ]);
        $this->originDriver = HubTransportDriver::create([
            'logistics_company_id' => $this->originCompany->id,
            'logistics_truck_id' => $this->originTruck->id,
            'name' => 'Origin Driver Private',
            'email' => 'origin.driver@cc.test',
            'status' => 'assigned',
            'license_number' => 'LIC-ORIGIN-1',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);

        $this->destinationTruck = LogisticsTruck::create([
            'logistics_company_id' => $this->destinationCompany->id,
            'ownership_type' => 'company',
            'plate_number' => 'DEST-PLATE-7722',
            'vehicle_type' => 'light truck',
            'model' => 'Isuzu Elf',
            'parcel_capacity' => 100,
            'capacity_kg' => 1500,
            'status' => 'assigned',
            'verification_status' => 'approved',
        ]);
        $this->destinationDriver = HubTransportDriver::create([
            'logistics_company_id' => $this->destinationCompany->id,
            'logistics_truck_id' => $this->destinationTruck->id,
            'name' => 'Destination Fleet Driver',
            'email' => 'destination.driver@cc.test',
            'status' => 'assigned',
            'license_number' => 'LIC-DEST-1',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
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

    private function createSchema(): void
    {
        $schema = Schema::connection('sqlite');

        foreach ([
            'hub_transfer_events', 'hub_transfer_parcels', 'hub_transfers',
            'deliveries', 'riders', 'hub_transport_drivers', 'logistics_trucks',
            'logistics_settings', 'logistics_centers', 'logistics_companies', 'users',
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

        $schema->create('riders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('license_plate')->nullable();
            $table->string('vehicle_verification')->nullable();
            $table->date('license_expiry')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_verified')->default(true);
            $table->boolean('is_online')->default(false);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        $schema->create('logistics_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('photo_path')->nullable();
            $table->text('preferences')->nullable();
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
            $table->timestamp('received_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('sorting_center_handoff_at')->nullable();
            $table->unsignedBigInteger('sorting_center_handoff_rider_id')->nullable();
            $table->timestamp('archived_at')->nullable();
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
