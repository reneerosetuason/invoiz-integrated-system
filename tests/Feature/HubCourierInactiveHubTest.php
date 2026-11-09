<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Hub Transport Driver API must not advance a transfer while either of its
 * hubs is inactive. Historical legs stay readable, but every custody-changing
 * endpoint re-checks the persisted origin and destination hubs.
 *
 * Runs on a self-contained in-memory SQLite schema.
 */
class HubCourierInactiveHubTest extends TestCase
{
    private LogisticsCompany $company;
    private LogisticsCenter $originHub;
    private LogisticsCenter $destinationHub;
    private LogisticsTruck $truck;
    private HubTransportDriver $driver;

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
        $this->seedFleet();

        Sanctum::actingAs($this->driver, ['hub-courier']);
    }

    public function test_every_mutation_is_rejected_when_the_origin_hub_is_inactive(): void
    {
        foreach ($this->mutations() as $endpoint) {
            [$transfer, $delivery] = $this->prepare($endpoint);
            $payload = $endpoint === 'load' ? ['tracking_number' => $delivery->tracking_number] : [];
            $this->originHub->update(['is_active' => false]);
            $this->destinationHub->update(['is_active' => true]);

            $before = $this->state($transfer);

            $this->postJson($this->url($transfer, $endpoint), $payload)
                ->assertStatus(422);

            $this->assertSame($before, $this->state($transfer->fresh()), "State changed for {$endpoint}.");
        }
    }

    public function test_every_mutation_is_rejected_when_the_destination_hub_is_inactive(): void
    {
        foreach ($this->mutations() as $endpoint) {
            [$transfer, $delivery] = $this->prepare($endpoint);
            $payload = $endpoint === 'load' ? ['tracking_number' => $delivery->tracking_number] : [];
            $this->originHub->update(['is_active' => true]);
            $this->destinationHub->update(['is_active' => false]);

            $before = $this->state($transfer);

            $this->postJson($this->url($transfer, $endpoint), $payload)
                ->assertStatus(422);

            $this->assertSame($before, $this->state($transfer->fresh()), "State changed for {$endpoint}.");
        }
    }

    public function test_every_mutation_is_permitted_when_both_hubs_are_active(): void
    {
        $this->originHub->update(['is_active' => true]);
        $this->destinationHub->update(['is_active' => true]);

        [$transfer] = $this->prepare('origin-arrival');
        $this->postJson($this->url($transfer, 'origin-arrival'))->assertOk();
        $this->assertNotNull($transfer->fresh()->origin_arrived_at);

        [$transfer, $delivery] = $this->prepare('load');
        $this->postJson($this->url($transfer, 'load'), ['tracking_number' => $delivery->tracking_number])->assertOk();
        $this->assertSame('loaded', HubTransferParcel::where('hub_transfer_id', $transfer->id)->value('loading_status'));

        [$transfer] = $this->prepare('complete-loading');
        $this->postJson($this->url($transfer, 'complete-loading'))->assertOk();
        $this->assertSame('ready_for_dispatch', $transfer->fresh()->status);

        [$transfer, $delivery] = $this->prepare('dispatch');
        $this->postJson($this->url($transfer, 'dispatch'))->assertOk();
        $this->assertSame('in_transit', $transfer->fresh()->status);
        $this->assertSame('dispatched', $delivery->fresh()->parcel_status);

        [$transfer] = $this->prepare('destination-arrival');
        $this->postJson($this->url($transfer, 'destination-arrival'))->assertOk();
        $fresh = $transfer->fresh();
        $this->assertSame('arrived', $fresh->status);
        $this->assertNotNull($fresh->destination_arrived_at);
    }

    public function test_client_supplied_hub_company_and_driver_ids_cannot_bypass_inactive_hubs(): void
    {
        $this->originHub->update(['is_active' => false]);
        $this->destinationHub->update(['is_active' => true]);

        [$transfer] = $this->prepare('origin-arrival');
        $spoof = [
            'origin_center_id' => $this->destinationHub->id,
            'destination_center_id' => $this->destinationHub->id,
            'logistics_company_id' => $this->company->id,
            'driver_id' => $this->driver->id,
            'truck_id' => $this->truck->id,
        ];
        $this->postJson($this->url($transfer, 'origin-arrival'), $spoof)->assertStatus(422);
        $this->assertNull($transfer->fresh()->origin_arrived_at);

        [$transfer, $delivery] = $this->prepare('load');
        $this->postJson($this->url($transfer, 'load'), $spoof + ['tracking_number' => $delivery->tracking_number])->assertStatus(422);
        $this->assertSame('pending', HubTransferParcel::where('hub_transfer_id', $transfer->id)->value('loading_status'));
    }

    public function test_transfer_stays_readable_while_a_hub_is_inactive(): void
    {
        [$transfer] = $this->prepare('in_transit');
        $this->destinationHub->update(['is_active' => false]);

        $this->getJson($this->url($transfer, 'show'))
            ->assertOk()
            ->assertJsonPath('transfer.id', $transfer->id)
            ->assertJsonPath('transfer.status', 'in_transit');

        $this->getJson('/api/logistics/courier/transfers')
            ->assertOk()
            ->assertJsonFragment(['reference' => $transfer->reference, 'status' => 'in_transit']);
    }

    // ------------------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------------------

    /** @return array<int, string> */
    private function mutations(): array
    {
        return ['origin-arrival', 'load', 'complete-loading', 'dispatch', 'destination-arrival'];
    }

    private function url(HubTransfer $transfer, string $endpoint): string
    {
        return $endpoint === 'show'
            ? '/api/logistics/courier/transfers/'.$transfer->id
            : '/api/logistics/courier/transfers/'.$transfer->id.'/'.$endpoint;
    }

    /** @return array{0: HubTransfer, 1: Delivery} */
    private function prepare(string $endpoint): array
    {
        switch ($endpoint) {
            case 'origin-arrival':
                return $this->makeTransfer('loading', null);
            case 'load':
                return $this->makeTransfer('loading', now(), 'pending');
            case 'complete-loading':
                return $this->makeTransfer('loading', now(), 'loaded');
            case 'dispatch':
                return $this->makeTransfer('ready_for_dispatch', now(), 'loaded', 'sorted');
            default:
                return $this->makeTransfer('in_transit', now(), 'loaded', 'dispatched');
        }
    }

    /** @return array{0: HubTransfer, 1: Delivery} */
    private function makeTransfer(
        string $status,
        mixed $originArrivedAt = null,
        string $loadingStatus = 'pending',
        string $parcelStatus = 'received',
    ): array {
        $delivery = Delivery::create([
            'tracking_number' => 'TRK-HT-'.str_pad((string) (++self::$sequence), 4, '0', STR_PAD_LEFT),
            'center_id' => $this->originHub->id,
            'destination_center_id' => $this->destinationHub->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => $parcelStatus,
            'weight' => 2,
        ]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-HUB-'.self::$sequence,
            'logistics_company_id' => $this->company->id,
            'origin_center_id' => $this->originHub->id,
            'destination_center_id' => $this->destinationHub->id,
            'truck_id' => $this->truck->id,
            'driver_id' => $this->driver->id,
            'status' => $status,
            'origin_arrived_at' => $originArrivedAt,
            'dispatched_at' => $status === 'in_transit' ? now() : null,
        ]);

        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => $loadingStatus,
            'receiving_status' => 'pending',
        ]);

        return [$transfer, $delivery];
    }

    /**
     * Custody-relevant state that no rejected request may change.
     *
     * @return array<string, mixed>
     */
    private function state(HubTransfer $transfer): array
    {
        $deliveryId = HubTransferParcel::where('hub_transfer_id', $transfer->id)->value('delivery_id');
        $delivery = Delivery::find($deliveryId);

        return [
            'status' => $transfer->status,
            'origin_arrived_at' => $transfer->origin_arrived_at?->toDateTimeString(),
            'dispatched_at' => $transfer->dispatched_at?->toDateTimeString(),
            'destination_arrived_at' => $transfer->destination_arrived_at?->toDateTimeString(),
            'received_at' => $transfer->received_at?->toDateTimeString(),
            'parcels' => HubTransferParcel::where('hub_transfer_id', $transfer->id)
                ->orderBy('id')
                ->get(['loading_status', 'receiving_status', 'condition'])
                ->map(fn ($entry) => $entry->only(['loading_status', 'receiving_status', 'condition']))
                ->all(),
            'delivery_center_id' => $delivery?->center_id,
            'delivery_parcel_status' => $delivery?->parcel_status,
        ];
    }

    private function seedFleet(): void
    {
        $this->company = LogisticsCompany::create(['name' => 'Courier Hub Logistics', 'status' => 'active']);

        $this->originHub = LogisticsCenter::create([
            'name' => 'Courier Origin Hub',
            'address' => '1 Origin Road',
            'is_active' => true,
            'logistics_company_id' => $this->company->id,
        ]);
        $this->destinationHub = LogisticsCenter::create([
            'name' => 'Courier Destination Hub',
            'address' => '2 Destination Road',
            'is_active' => true,
            'logistics_company_id' => $this->company->id,
        ]);

        $this->truck = LogisticsTruck::create([
            'logistics_company_id' => $this->company->id,
            'ownership_type' => 'company',
            'plate_number' => 'HUB-TRK-1001',
            'vehicle_type' => 'truck',
            'parcel_capacity' => 50,
            'capacity_kg' => 1000,
            'status' => 'assigned',
            'verification_status' => 'approved',
        ]);
        $this->driver = HubTransportDriver::create([
            'logistics_company_id' => $this->company->id,
            'logistics_truck_id' => $this->truck->id,
            'name' => 'Hub Courier Driver',
            'email' => 'hub.courier@inactive.test',
            'password' => Hash::make('secret123'),
            'status' => 'assigned',
            'is_online' => true,
        ]);
    }

    private function createSchema(): void
    {
        $schema = Schema::connection('sqlite');

        foreach ([
            'hub_transfer_events', 'hub_transfer_parcels', 'hub_transfers', 'delivery_status_logs',
            'deliveries', 'rider_applications', 'hub_transport_drivers', 'logistics_trucks',
            'logistics_centers', 'logistics_companies',
        ] as $table) {
            $schema->dropIfExists($table);
        }

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
            $table->text('verification_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('hub_transport_drivers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->unsignedBigInteger('logistics_truck_id')->nullable();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->string('license_number')->nullable();
            $table->string('avatar')->nullable();
            $table->string('status')->default('available');
            $table->boolean('is_online')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        $schema->create('rider_applications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_transport_driver_id')->nullable();
            $table->unsignedBigInteger('logistics_center_id')->nullable();
            $table->string('email')->nullable();
            $table->string('application_type')->nullable();
            $table->string('status')->default('pending');
            $table->date('license_expiry')->nullable();
            $table->timestamps();
        });

        $schema->create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->unique();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->string('status')->default('waiting_for_rider');
            $table->string('parcel_status')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->timestamp('archived_at')->nullable();
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
