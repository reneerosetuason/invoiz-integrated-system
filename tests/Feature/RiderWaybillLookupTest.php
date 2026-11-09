<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The rider parcel scanner sends the tracking number encoded in the single
 * Code128 waybill barcode. These tests pin the lookup contract: the same
 * canonical tracking number, rider-ownership authorization, and read-only
 * behavior (a scan never creates a parcel or a status event).
 */
class RiderWaybillLookupTest extends TestCase
{
    private const TRACKING = 'TRK-20261107-AB12';

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
    }

    private function createSchema(): void
    {
        $schema = Schema::connection('sqlite');

        foreach ([
            'delivery_failures', 'delivery_proofs', 'delivery_status_logs',
            'delivery_items', 'deliveries', 'orders', 'service_areas',
            'logistics_centers', 'riders', 'users',
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
            $table->string('status')->default('active');
            $table->string('account_status')->default('active');
            $table->string('phone')->nullable();
            $table->timestamp('email_verified_at')->nullable();
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
            $table->string('status')->default('active');
            $table->boolean('is_verified')->default(true);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        $schema->create('logistics_centers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
        });

        $schema->create('service_areas', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $schema->create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('buyer_id')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->timestamps();
        });

        $schema->create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('tracking_number')->unique();
            $table->string('status')->default('assigned');
            $table->string('parcel_status')->nullable();
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
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->decimal('amount_to_collect', 10, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->string('pickup_pin')->nullable();
            $table->text('notes')->nullable();
            $table->text('delivery_notes')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('reattempt_requested_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->string('return_reason')->nullable();
            $table->timestamp('sorting_center_handoff_at')->nullable();
            $table->unsignedBigInteger('sorting_center_handoff_rider_id')->nullable();
            $table->timestamp('sorting_center_pickup_at')->nullable();
            $table->unsignedBigInteger('sorting_center_pickup_rider_id')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('sorted_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
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
    }

    private function riderUser(string $email): array
    {
        $user = User::create([
            'name' => 'Rider ' . $email,
            'email' => $email,
            'password' => Hash::make('secret'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $rider = Rider::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $email,
            'status' => 'active',
            'is_verified' => true,
            'approved_at' => now(),
        ]);

        return [$user, $rider];
    }

    private function deliveryFor(int $riderId): Delivery
    {
        return Delivery::create([
            'tracking_number' => self::TRACKING,
            'rider_id' => $riderId,
            'status' => 'assigned',
            'sender_name' => 'INVOIZ Seller',
            'recipient_name' => 'Test Recipient',
            'recipient_address' => '12 Sample Street',
            'delivery_fee' => 50,
            'amount_to_collect' => 300,
        ]);
    }

    public function test_rider_finds_the_assigned_parcel_using_the_code128_value(): void
    {
        [$user, $rider] = $this->riderUser('assigned@example.test');
        $delivery = $this->deliveryFor($rider->id);
        Sanctum::actingAs($user, ['rider']);

        // Barcode payloads are normalized exactly like manual entry.
        $this->getJson('/api/logistics/rider/deliveries/lookup?tracking_number=' . strtolower(self::TRACKING))
            ->assertOk()
            ->assertJsonPath('delivery.id', $delivery->id)
            ->assertJsonPath('delivery.tracking_number', self::TRACKING);
    }

    public function test_rider_cannot_lookup_a_parcel_assigned_to_another_rider(): void
    {
        [, $otherRider] = $this->riderUser('other@example.test');
        $delivery = $this->deliveryFor($otherRider->id);

        [$user] = $this->riderUser('intruder@example.test');
        Sanctum::actingAs($user, ['rider']);

        $this->getJson('/api/logistics/rider/deliveries/lookup?tracking_number=' . $delivery->tracking_number)
            ->assertForbidden();
    }

    public function test_unknown_barcode_value_returns_not_found(): void
    {
        [$user] = $this->riderUser('unknown@example.test');
        Sanctum::actingAs($user, ['rider']);

        $this->getJson('/api/logistics/rider/deliveries/lookup?tracking_number=TRK-20261107-ZZZZ')
            ->assertNotFound();
    }

    public function test_non_rider_account_cannot_use_the_rider_lookup(): void
    {
        $user = User::create([
            'name' => 'Logistics Staff',
            'email' => 'staff@example.test',
            'password' => Hash::make('secret'),
            'role' => 'logistics_staff',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        Sanctum::actingAs($user, ['rider']);

        $this->getJson('/api/logistics/rider/deliveries/lookup?tracking_number=' . self::TRACKING)
            ->assertForbidden();
    }

    public function test_missing_rider_token_ability_is_rejected(): void
    {
        [$user] = $this->riderUser('noability@example.test');
        Sanctum::actingAs($user, []);

        $this->getJson('/api/logistics/rider/deliveries/lookup?tracking_number=' . self::TRACKING)
            ->assertForbidden();
    }

    public function test_lookup_never_creates_a_parcel_or_status_event(): void
    {
        [$user, $rider] = $this->riderUser('readonly@example.test');
        $this->deliveryFor($rider->id);
        Sanctum::actingAs($user, ['rider']);

        $deliveriesBefore = DB::table('deliveries')->count();
        $logsBefore = DB::table('delivery_status_logs')->count();

        $this->getJson('/api/logistics/rider/deliveries/lookup?tracking_number=' . self::TRACKING)
            ->assertOk();

        $this->assertSame($deliveriesBefore, DB::table('deliveries')->count());
        $this->assertSame($logsBefore, DB::table('delivery_status_logs')->count());
        $this->assertDatabaseHas('deliveries', [
            'tracking_number' => self::TRACKING,
            'status' => 'assigned',
        ]);
    }
}
