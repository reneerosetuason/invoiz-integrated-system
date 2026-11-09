<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Facility authorization for the unified mobile app's logistics scanner.
 *
 * Uses its own in-memory SQLite schema so it never touches the shared MySQL
 * databases and never runs migrations. The endpoints under test are the real
 * /api/logistics/scan/* routes with the real Sanctum + logistics.mobile
 * middleware stack.
 */
class LogisticsScanAuthorizationTest extends TestCase
{
    private int $seq = 0;

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

        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        $this->createSchema();
    }

    private function createSchema(): void
    {
        $schema = Schema::connection('sqlite');

        $schema->create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->string('role')->default('buyer');
            $table->string('status')->default('active');
            $table->string('account_status')->default('active');
            $table->string('approval_status')->nullable();
            $table->string('phone')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('logistics_company_id')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $schema->create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->nullable()->unique();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('seller_id')->nullable();
            $table->unsignedBigInteger('rider_id')->nullable();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('destination_center_id')->nullable();
            $table->unsignedBigInteger('service_area_id')->nullable();
            $table->string('status')->default('waiting_for_rider');
            $table->string('parcel_status')->default('received');
            $table->string('sender_name')->nullable();
            $table->string('sender_phone')->nullable();
            $table->text('sender_address')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('recipient_phone')->nullable();
            $table->text('recipient_address')->nullable();
            $table->string('package_type')->nullable();
            $table->decimal('weight', 8, 2)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('sorting_center_handoff_at')->nullable();
            $table->unsignedBigInteger('sorting_center_handoff_rider_id')->nullable();
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

        // Read by the Logistics Web middleware that wraps scan-verify.
        $schema->create('logistics_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('timezone')->default('UTC');
            $table->timestamps();
        });
    }

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
            'name' => $name,
            'address' => '1 Test Road',
            'city' => $name,
            'province' => 'Laguna',
            'is_active' => true,
            'logistics_company_id' => $company->id,
        ]);
    }

    private function staff(LogisticsCompany $company, LogisticsCenter $center): User
    {
        $this->seq++;

        return User::create([
            'name' => 'Logistics Staff '.$this->seq,
            'email' => 'scan-staff-'.$this->seq.'-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'logistics_staff',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'logistics_company_id' => $company->id,
            'center_id' => $center->id,
            'email_verified_at' => now(),
        ]);
    }

    private function delivery(LogisticsCenter $center, array $extra = []): Delivery
    {
        return Delivery::create(array_merge([
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'tracking_number' => 'TRK-'.now()->format('Ymd').'-'.strtoupper(bin2hex(random_bytes(2))),
            'sender_name' => 'Test Sender',
            'recipient_name' => 'Test Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'center_id' => $center->id,
            'received_at' => now(),
        ], $extra));
    }

    private function logCount(Delivery $delivery): int
    {
        return DeliveryStatusLog::where('delivery_id', $delivery->id)->count();
    }

    // ------------------------------------------------------------------
    // 1. Correct facility: lookup + confirm succeed and log history.
    // ------------------------------------------------------------------

    public function test_staff_at_the_parcel_hub_can_lookup_and_confirm(): void
    {
        $company = $this->company('Company A');
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);
        $delivery = $this->delivery($calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('facility.id', $calamba->id)
            ->assertJsonPath('delivery.current_hub.id', $calamba->id);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => strtolower('  '.$delivery->tracking_number.'  '),
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('already', false)
            ->assertJsonPath('facility.id', $calamba->id)
            ->assertJsonPath('delivery.parcel_status', 'scanned')
            ->assertJsonPath('history.0.status', 'scanned');

        $fresh = $delivery->fresh();
        $this->assertSame('scanned', $fresh->parcel_status);
        $this->assertNotNull($fresh->scanned_at);
        $this->assertSame($calamba->id, (int) $fresh->center_id);
        $this->assertSame(1, $this->logCount($delivery));
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'scanned',
            'location' => $calamba->name,
            'changed_by' => $staff->id,
        ]);
    }

    // ------------------------------------------------------------------
    // 2. Same company, different hub: rejected even when the parcel is
    //    destined for the staff's hub (destination != current location).
    // ------------------------------------------------------------------

    public function test_same_company_other_hub_is_rejected_when_parcel_is_physically_elsewhere(): void
    {
        $company = $this->company('Company A');
        $calamba = $this->center($company, 'Calamba Hub');
        $pila = $this->center($company, 'Pila Hub');
        $staff = $this->staff($company, $calamba);

        $delivery = $this->delivery($pila, [
            'destination_center_id' => $calamba->id,
        ]);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');

        $fresh = $delivery->fresh();
        $this->assertSame('received', $fresh->parcel_status);
        $this->assertNull($fresh->scanned_at);
        $this->assertSame($pila->id, (int) $fresh->center_id);
        $this->assertSame(0, $this->logCount($delivery));
    }

    // ------------------------------------------------------------------
    // 3. Different logistics company: rejected.
    // ------------------------------------------------------------------

    public function test_different_logistics_company_is_rejected(): void
    {
        $companyA = $this->company('Company A');
        $companyB = $this->company('Company B');
        $calamba = $this->center($companyA, 'Calamba Hub');
        $foreign = $this->center($companyB, 'Foreign Hub');
        $staff = $this->staff($companyA, $calamba);
        $delivery = $this->delivery($foreign);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');

        $this->assertSame('received', $delivery->fresh()->parcel_status);
        $this->assertSame(0, $this->logCount($delivery));
    }

    // ------------------------------------------------------------------
    // 4. Client-supplied facility/company ids are ignored.
    // ------------------------------------------------------------------

    public function test_client_supplied_facility_ids_cannot_redirect_the_scan(): void
    {
        $companyA = $this->company('Company A');
        $companyB = $this->company('Company B');
        $calamba = $this->center($companyA, 'Calamba Hub');
        $foreign = $this->center($companyB, 'Foreign Hub');
        $staff = $this->staff($companyA, $calamba);
        $delivery = $this->delivery($calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
            'facility_id' => $foreign->id,
            'center_id' => $foreign->id,
            'logistics_center_id' => $foreign->id,
            'logistics_company_id' => $companyB->id,
        ])
            ->assertOk()
            ->assertJsonPath('already', false)
            ->assertJsonPath('facility.id', $calamba->id);

        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'scanned',
            'location' => $calamba->name,
            'changed_by' => $staff->id,
        ]);
        $this->assertDatabaseMissing('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'location' => $foreign->name,
        ]);
    }

    // ------------------------------------------------------------------
    // 5. Ineligible / unverified custody states are rejected.
    // ------------------------------------------------------------------

    public function test_parcel_that_is_not_received_is_rejected(): void
    {
        $company = $this->company('Company A');
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);
        $delivery = $this->delivery($calamba, [
            'parcel_status' => 'pending_arrival',
            'received_at' => null,
        ]);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_state');

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_state');

        $this->assertSame('pending_arrival', $delivery->fresh()->parcel_status);
        $this->assertSame(0, $this->logCount($delivery));
    }

    public function test_order_parcel_without_pickup_handoff_is_rejected_as_unverified(): void
    {
        $company = $this->company('Company A');
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);
        $delivery = $this->delivery($calamba, ['order_id' => 99]);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'receipt_unverified');

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertStatus(409)
            ->assertJsonPath('error', 'receipt_unverified');

        $this->assertSame('received', $delivery->fresh()->parcel_status);
        $this->assertSame(0, $this->logCount($delivery));
    }

    public function test_unknown_and_malformed_tracking_numbers_are_rejected(): void
    {
        $company = $this->company('Company A');
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => 'TRK-20260101-ZZZZ',
        ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');

        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => 'NOT-A-TRACKING-NUMBER',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tracking_number');
    }

    // ------------------------------------------------------------------
    // 6. Duplicate confirmation is idempotent.
    // ------------------------------------------------------------------

    public function test_duplicate_confirmation_writes_one_log_only(): void
    {
        $company = $this->company('Company A');
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);
        $delivery = $this->delivery($calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])->assertOk()->assertJsonPath('already', false);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('already', true)
            ->assertJsonPath('eligible', false);

        $this->assertSame(1, $this->logCount($delivery));
    }

    // ------------------------------------------------------------------
    // 9. A mobile scan is the same record Logistics Web reads.
    // ------------------------------------------------------------------

    public function test_successful_mobile_scan_is_visible_to_the_web_counter(): void
    {
        $company = $this->company('Company A');
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);
        $delivery = $this->delivery($calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])->assertOk()->assertJsonPath('already', false);

        // The Logistics Web counter reads the same delivery row and reports
        // the mobile scan as already completed.
        $this->postJson('/logistics/deliveries/scan-verify', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('already', true);

        $this->assertSame(1, $this->logCount($delivery));
        $this->assertSame(1, $delivery->fresh()->statusLogs()->count());
        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'scanned',
            'location' => $calamba->name,
            'changed_by' => $staff->id,
        ]);
    }

    // ------------------------------------------------------------------
    // 10. Unauthorized attempts leave no scan records or mutations.
    // ------------------------------------------------------------------

    public function test_unauthorized_attempts_create_no_logs_or_status_changes(): void
    {
        $companyA = $this->company('Company A');
        $companyB = $this->company('Company B');
        $calamba = $this->center($companyA, 'Calamba Hub');
        $pila = $this->center($companyA, 'Pila Hub');
        $foreign = $this->center($companyB, 'Foreign Hub');
        $staff = $this->staff($companyA, $calamba);

        $sameCompany = $this->delivery($pila);
        $otherCompany = $this->delivery($foreign);

        Sanctum::actingAs($staff, ['logistics']);

        foreach ([$sameCompany, $otherCompany] as $delivery) {
            $this->postJson('/api/logistics/scan/lookup', [
                'tracking_number' => $delivery->tracking_number,
            ])->assertStatus(403);

            $this->postJson('/api/logistics/scan/confirm', [
                'tracking_number' => $delivery->tracking_number,
            ])->assertStatus(403);

            $fresh = $delivery->fresh();
            $this->assertSame('received', $fresh->parcel_status);
            $this->assertNull($fresh->scanned_at);
            $this->assertSame(0, $this->logCount($delivery));
        }

        $this->assertSame(0, DeliveryStatusLog::count());
    }

    // ------------------------------------------------------------------
    // Context resolves the facility from the account, not the request.
    // ------------------------------------------------------------------

    public function test_scan_context_reports_the_authenticated_facility_and_company(): void
    {
        $company = $this->company('Company A');
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->getJson('/api/logistics/scan/context?facility_id=999&center_id=999')
            ->assertOk()
            ->assertJsonPath('account_type', 'logistics_staff')
            ->assertJsonPath('logistics.scope', 'hub')
            ->assertJsonPath('logistics.facility.id', $calamba->id)
            ->assertJsonPath('logistics.company.id', $company->id);
    }
}