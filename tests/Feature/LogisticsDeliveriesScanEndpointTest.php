<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\LogisticsCenter;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LogisticsDeliveriesScanEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // This test intentionally builds a reduced schema. Keep its DDL on an
        // ephemeral connection even when the suite is launched with the
        // persistent MySQL test configuration.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->assertContains(
            'sqlite',
            \PDO::getAvailableDrivers(),
            'SQLite PDO is required for this isolated fixture; no schema operations were run.'
        );
        $this->assertSame('sqlite', DB::getDefaultConnection());
        $sqlite = DB::connection('sqlite');
        $this->assertSame('sqlite', $sqlite->getDriverName());
        $this->assertSame(':memory:', $sqlite->getDatabaseName());
        $pdo = $sqlite->getPdo();
        $this->assertSame('sqlite', $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));
        $mainDatabase = collect($pdo->query('PRAGMA database_list')->fetchAll(\PDO::FETCH_ASSOC))
            ->firstWhere('name', 'main');
        $this->assertSame('', $mainDatabase['file'] ?? null, 'The fixture database must be in memory.');

        $this->setUpDatabaseSchema();
    }

    private function setUpDatabaseSchema(): void
    {
        $schema = Schema::connection('sqlite');
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
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address')->nullable();
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
    }

    private function createStaff(?int $centerId = null, string $role = 'logistics_staff'): User
    {
        return User::create([
            'name' => 'Staff Member',
            'email' => 'staff_' . uniqid() . '@example.com',
            'password' => Hash::make('password123'),
            'role' => $role,
            'center_id' => $centerId,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_scan_page_renders_with_relative_submit_url(): void
    {
        $center = LogisticsCenter::create(['name' => 'Center North', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $response = $this->actingAs($staff)->get('/logistics/deliveries/scan?expect=TRK-20261017-ZHB0');

        $response->assertOk();
        $response->assertSee('TRK-20261017-ZHB0', false);
        // Verify relative route is rendered in both data-submit-url attribute and data-config JSON
        $response->assertSee('/logistics/deliveries/scan-verify', false);
        $response->assertDontSee('http://localhost:8000/logistics/deliveries/scan-verify', false);
    }

    public function test_scan_verify_succeeds_and_updates_status_to_scanned(): void
    {
        $center = LogisticsCenter::create(['name' => 'Center North', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-20261017-ZHB0',
            'parcel_status' => 'received',
            'center_id' => $center->id,
            'recipient_name' => 'Juan Dela Cruz',
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => 'TRK-20261017-ZHB0',
            ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'message' => 'Parcel scanned successfully.',
            'delivery' => [
                'tracking_number' => 'TRK-20261017-ZHB0',
                'recipient_name' => 'Juan Dela Cruz',
                'parcel_status' => 'scanned',
                'show_url' => '/logistics/deliveries/' . $delivery->id,
            ],
        ]);

        $this->assertSame('scanned', $delivery->fresh()->parcel_status);
        $this->assertNotNull($delivery->fresh()->scanned_at);
    }

    public function test_scan_verify_accepts_tracking_with_whitespace_and_lowercase(): void
    {
        $center = LogisticsCenter::create(['name' => 'Center North', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-20261017-ZHB0',
            'parcel_status' => 'received',
            'center_id' => $center->id,
            'recipient_name' => 'Maria Clara',
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => '  trk-20261017-zhb0  ',
            ]);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertSame('scanned', $delivery->fresh()->parcel_status);
    }

    public function test_scan_verify_returns_already_scanned_when_already_in_scanned_state(): void
    {
        $center = LogisticsCenter::create(['name' => 'Center North', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-20261017-ZHB0',
            'parcel_status' => 'scanned',
            'scanned_at' => now(),
            'center_id' => $center->id,
            'recipient_name' => 'Juan Dela Cruz',
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => 'TRK-20261017-ZHB0',
            ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'already' => true,
        ]);
    }

    public function test_scan_verify_rejects_parcel_belonging_to_another_center(): void
    {
        $centerA = LogisticsCenter::create(['name' => 'Center A', 'is_active' => true]);
        $centerB = LogisticsCenter::create(['name' => 'Center B', 'is_active' => true]);
        $staffA = $this->createStaff($centerA->id);

        Delivery::create([
            'tracking_number' => 'TRK-20261017-ZHB0',
            'parcel_status' => 'received',
            'center_id' => $centerB->id,
            'recipient_name' => 'Other Center Recipient',
        ]);

        $response = $this->actingAs($staffA)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => 'TRK-20261017-ZHB0',
            ]);

        $response->assertStatus(403);
        $response->assertJson([
            'ok' => false,
            'error' => 'forbidden',
        ]);
    }

    public function test_scan_verify_rejects_non_existent_parcel_with_404(): void
    {
        $center = LogisticsCenter::create(['name' => 'Center North', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => 'TRK-20261017-9999',
            ]);

        $response->assertStatus(404);
        $response->assertJson([
            'ok' => false,
            'error' => 'not_found',
        ]);
    }

    public function test_scan_verify_rejects_invalid_tracking_number_format_with_422(): void
    {
        $center = LogisticsCenter::create(['name' => 'Center North', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => 'INVALID-FORMAT',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tracking_number');
    }

    public function test_scan_verify_rejects_parcel_not_in_received_state(): void
    {
        $center = LogisticsCenter::create(['name' => 'Center North', 'is_active' => true]);
        $staff = $this->createStaff($center->id);

        Delivery::create([
            'tracking_number' => 'TRK-20261017-ZHB0',
            'parcel_status' => 'pending_arrival',
            'center_id' => $center->id,
            'recipient_name' => 'Pending Recipient',
        ]);

        $response = $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => 'TRK-20261017-ZHB0',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'ok' => false,
            'error' => 'invalid_state',
        ]);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/logistics/deliveries/scan-verify', [
            'tracking_number' => 'TRK-20261017-ZHB0',
        ]);

        $response->assertStatus(401);
    }

    public function test_non_staff_user_cannot_access_scan_verify(): void
    {
        $buyer = $this->createStaff(null, 'buyer');

        $response = $this->actingAs($buyer)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => 'TRK-20261017-ZHB0',
            ]);

        $response->assertStatus(403);
    }
}
