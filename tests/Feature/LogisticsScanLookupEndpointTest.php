<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\LogisticsCenter;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Focused feature tests for POST /logistics/deliveries/scan-lookup.
 *
 * Verifies that scan-lookup is purely read-only, checks authorization and
 * state requirements, and ensures that state transitions remain exclusively
 * within scan-verify.
 */
class LogisticsScanLookupEndpointTest extends TestCase
{
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
        $sqlite = DB::connection('sqlite');
        $this->assertSame(':memory:', $sqlite->getDatabaseName());

        $this->setUpDatabaseSchema();
    }

    private function setUpDatabaseSchema(): void
    {
        $schema = Schema::connection('sqlite');

        $schema->dropIfExists('hub_transfer_parcels');
        $schema->dropIfExists('hub_transfers');
        $schema->dropIfExists('delivery_status_logs');
        $schema->dropIfExists('logistics_settings');
        $schema->dropIfExists('deliveries');
        $schema->dropIfExists('logistics_centers');
        $schema->dropIfExists('users');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('logistics_staff');
            $table->string('status')->default('active');
            $table->string('account_status')->default('active');
            $table->unsignedBigInteger('center_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        $schema->create('logistics_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('logistics_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });

        $schema->create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->nullable()->unique();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->string('parcel_status')->default('received');
            $table->string('status')->default('waiting_for_rider');
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('sorting_center_handoff_at')->nullable();
            $table->unsignedBigInteger('sorting_center_handoff_rider_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
        });

        $schema->create('delivery_status_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_id');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamps();
        });

        $schema->create('hub_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->nullable();
            $table->unsignedBigInteger('origin_center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->string('status')->default('in_transit');
            $table->timestamps();
        });

        $schema->create('hub_transfer_parcels', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('hub_transfer_id');
            $table->unsignedBigInteger('delivery_id');
            $table->string('loading_status')->default('loaded');
            $table->string('receiving_status')->default('pending');
            $table->timestamps();
        });
    }

    private function createStaff(?int $centerId = null, string $role = 'logistics_staff'): User
    {
        return User::create([
            'name' => 'Staff Member',
            'email' => 'staff_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'center_id' => $centerId,
            'status' => 'active',
            'account_status' => 'active',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_valid_received_parcel_returns_details_without_changing_status_or_creating_logs(): void
    {
        $center = LogisticsCenter::create(['name' => 'Central Hub', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-20261109-AA11',
            'parcel_status' => 'received',
            'status' => 'waiting_for_rider',
            'center_id' => $center->id,
            'recipient_name' => 'Target Customer',
            'recipient_address' => '100 Rizal Street',
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-lookup', [
                'tracking_number' => 'TRK-20261109-AA11',
            ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'can_confirm' => true,
            'message' => 'Parcel found. Review the details and confirm to verify it.',
            'delivery' => [
                'tracking_number' => 'TRK-20261109-AA11',
                'recipient_name' => 'Target Customer',
                'parcel_status' => 'received',
            ],
        ]);

        // Assert strictly read-only: no changes to parcel status, scanned_at, or logs.
        $fresh = $delivery->fresh();
        $this->assertSame('received', $fresh->parcel_status);
        $this->assertNull($fresh->scanned_at);
        $this->assertSame(0, DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count());
    }

    public function test_parcel_belonging_to_another_logistics_center_is_rejected(): void
    {
        $centerA = LogisticsCenter::create(['name' => 'Hub A', 'is_active' => true]);
        $centerB = LogisticsCenter::create(['name' => 'Hub B', 'is_active' => true]);
        $staffA = $this->createStaff($centerA->id);

        Delivery::create([
            'tracking_number' => 'TRK-20261109-BB22',
            'parcel_status' => 'received',
            'center_id' => $centerB->id,
            'recipient_name' => 'Customer B',
        ]);

        $response = $this->actingAs($staffA)
            ->postJson('/logistics/deliveries/scan-lookup', [
                'tracking_number' => 'TRK-20261109-BB22',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'error' => 'forbidden',
            'message' => 'This parcel belongs to another logistics center.',
        ]);
    }

    public function test_parcel_with_active_hub_transfer_is_rejected(): void
    {
        $center = LogisticsCenter::create(['name' => 'Origin Hub', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-20261109-CC33',
            'parcel_status' => 'received',
            'center_id' => $center->id,
            'recipient_name' => 'Transfer Customer',
        ]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-20261109-0001',
            'origin_center_id' => $center->id,
            'destination_center_id' => 999,
            'status' => 'in_transit',
        ]);

        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'pending',
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-lookup', [
                'tracking_number' => 'TRK-20261109-CC33',
            ]);

        $response->assertStatus(409);
        $response->assertJson([
            'ok' => false,
            'error' => 'transfer_in_progress',
            'message' => 'This parcel remains in fleet-transfer custody until receiving is finalized.',
        ]);
    }

    public function test_parcel_not_yet_received_is_rejected(): void
    {
        $center = LogisticsCenter::create(['name' => 'Hub Central', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        Delivery::create([
            'tracking_number' => 'TRK-20261109-DD44',
            'parcel_status' => 'pending_arrival',
            'center_id' => $center->id,
            'recipient_name' => 'Unreceived Customer',
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-lookup', [
                'tracking_number' => 'TRK-20261109-DD44',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'error' => 'invalid_state',
        ]);
    }

    public function test_invalid_tracking_number_format_is_rejected(): void
    {
        $center = LogisticsCenter::create(['name' => 'Hub Central', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-lookup', [
                'tracking_number' => 'NOT-A-VALID-TRACKING-NUMBER',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tracking_number');
    }

    public function test_non_existent_tracking_number_returns_not_found(): void
    {
        $center = LogisticsCenter::create(['name' => 'Hub Central', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-lookup', [
                'tracking_number' => 'TRK-20261109-9999',
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => 'not_found',
        ]);
    }

    public function test_unauthorized_users_cannot_use_the_endpoint(): void
    {
        // 1. Unauthenticated request
        $unauthResponse = $this->postJson('/logistics/deliveries/scan-lookup', [
            'tracking_number' => 'TRK-20261109-AA11',
        ]);
        $unauthResponse->assertStatus(401);

        // 2. Non-staff user (e.g. buyer)
        $buyer = $this->createStaff(null, 'buyer');
        $buyerResponse = $this->actingAs($buyer)
            ->postJson('/logistics/deliveries/scan-lookup', [
                'tracking_number' => 'TRK-20261109-AA11',
            ]);
        $buyerResponse->assertStatus(403);
    }

    public function test_scan_verify_is_still_responsible_for_changing_parcel_status(): void
    {
        $center = LogisticsCenter::create(['name' => 'Central Hub', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-20261109-EE55',
            'parcel_status' => 'received',
            'status' => 'waiting_for_rider',
            'center_id' => $center->id,
            'recipient_name' => 'Confirmed Customer',
        ]);

        // Step 1: Scan lookup - should return details but leave state untouched.
        $lookupResponse = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-lookup', [
                'tracking_number' => 'TRK-20261109-EE55',
            ]);

        $lookupResponse->assertOk();
        $lookupResponse->assertJson(['ok' => true, 'can_confirm' => true]);

        $this->assertSame('received', $delivery->fresh()->parcel_status);
        $this->assertNull($delivery->fresh()->scanned_at);
        $this->assertSame(0, DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count());

        // Step 2: Separate confirmation through scan-verify - transitions status and writes log.
        $verifyResponse = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => 'TRK-20261109-EE55',
            ]);

        $verifyResponse->assertOk();
        $verifyResponse->assertJson(['ok' => true, 'message' => 'Parcel scanned successfully.']);

        $confirmed = $delivery->fresh();
        $this->assertSame('scanned', $confirmed->parcel_status);
        $this->assertNotNull($confirmed->scanned_at);
        $this->assertGreaterThanOrEqual(1, DB::table('delivery_status_logs')->where('delivery_id', $delivery->id)->count());
    }

    public function test_scan_returns_origin_destination_condition_and_warns_on_missing_or_duplicate_parcels(): void
    {
        $origin = LogisticsCenter::create(['name' => 'Origin Hub', 'is_active' => true]);
        $destination = LogisticsCenter::create(['name' => 'Destination Hub', 'is_active' => true]);
        $staff = $this->createStaff($origin->id);

        $goodParcel = Delivery::create([
            'tracking_number' => 'TRK-20261109-GD01',
            'parcel_status' => 'received',
            'status' => 'waiting_for_rider',
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'recipient_name' => 'Maria Buyer',
        ]);

        $lookup = $this->actingAs($staff)->postJson('/logistics/deliveries/scan-lookup', [
            'tracking_number' => 'TRK-20261109-GD01',
        ]);
        $lookup->assertOk()
            ->assertJsonPath('delivery.tracking_number', 'TRK-20261109-GD01')
            ->assertJsonPath('delivery.origin_hub', 'Origin Hub')
            ->assertJsonPath('delivery.destination_hub', 'Destination Hub')
            ->assertJsonPath('delivery.condition', 'Good')
            ->assertJsonPath('delivery.condition_code', 'good');

        // Duplicate scan warning when already scanned
        $goodParcel->update(['parcel_status' => 'scanned', 'scanned_at' => now()]);
        $duplicate = $this->actingAs($staff)->postJson('/logistics/deliveries/scan-lookup', [
            'tracking_number' => 'TRK-20261109-GD01',
        ]);
        $duplicate->assertOk()
            ->assertJsonPath('already', true)
            ->assertJsonPath('warning_type', 'duplicate');

        // Missing parcel is blocked with warning_type = 'missing'
        Delivery::create([
            'tracking_number' => 'TRK-20261109-MS01',
            'parcel_status' => 'received',
            'status' => 'lost',
            'center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'recipient_name' => 'Lost Recipient',
        ]);

        $missing = $this->actingAs($staff)->postJson('/logistics/deliveries/scan-lookup', [
            'tracking_number' => 'TRK-20261109-MS01',
        ]);
        $missing->assertStatus(409)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('error', 'parcel_missing')
            ->assertJsonPath('warning_type', 'missing')
            ->assertJsonPath('delivery.condition', 'Missing');
    }
}
