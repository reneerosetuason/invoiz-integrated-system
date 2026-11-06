<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\User;
use App\Support\AccountEmailGuard;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Covers the unified mobile app's logistics scanning API and the backend
 * role resolution that routes each account type to its own surface.
 *
 * Sandbox only (see Tests\TestCase). Runs inside DatabaseTransactions so the
 * shared sandbox schema is never rebuilt.
 */
class LogisticsMobileScanApiTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function company(string $name = 'Mobile Scan Co'): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => $name.' '.bin2hex(random_bytes(3)),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company, string $name): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name.' '.bin2hex(random_bytes(3)),
            'address' => '1 Test Road',
            'city' => $name,
            'province' => 'Laguna',
            'is_active' => true,
            'logistics_company_id' => $company->id,
        ]);
    }

    private function staff(LogisticsCompany $company, LogisticsCenter $center, array $overrides = []): User
    {
        $this->seq++;

        return User::create(array_merge([
            'name' => 'Logistics Staff '.$this->seq,
            'email' => 'logistics-staff-'.$this->seq.'-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'logistics_staff',
            'is_admin' => false,
            'logistics_company_id' => $company->id,
            'center_id' => $center->id,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ], $overrides));
    }

    private function platformAdmin(): User
    {
        $this->seq++;

        return User::create([
            'name' => 'Platform Admin '.$this->seq,
            'email' => 'platform-admin-'.$this->seq.'-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    /** The designated global Logistics Admin is a fixed identity (id 18). */
    private function designatedLogisticsAdmin(): User
    {
        $admin = new User([
            'name' => 'INVOIZ Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'password' => Hash::make('secret123'),
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->id = User::GLOBAL_LOGISTICS_ADMIN_ID;

        return $admin;
    }

    private function riderUser(LogisticsCenter $center): User
    {
        $this->seq++;
        $user = User::create([
            'name' => 'Delivery Rider '.$this->seq,
            'email' => 'delivery-rider-'.$this->seq.'-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        Rider::create([
            'user_id' => $user->id,
            'center_id' => $center->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_online' => true,
            'approved_at' => now(),
            'is_verified' => true,
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear(),
        ]);

        return $user;
    }

    private function driver(LogisticsCompany $company): HubTransportDriver
    {
        $this->seq++;

        return HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Hub Driver '.$this->seq,
            'email' => 'hub-driver-'.$this->seq.'-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => 'secret123',
            'phone' => '09171111111',
            'status' => 'available',
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

    // ---------------------------------------------------------------------
    // Role resolution / login
    // ---------------------------------------------------------------------

    public function test_logistics_staff_login_resolves_facility_and_logistics_token(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);

        $response = $this->postJson('/api/logistics/login', [
            'email' => strtoupper($staff->email),
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('account_type', 'logistics_staff')
            ->assertJsonPath('logistics.scope', 'hub')
            ->assertJsonPath('logistics.can_scan', true)
            ->assertJsonPath('logistics.facility.id', $calamba->id)
            ->assertJsonPath('logistics.company.id', $company->id);

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame(
            ['logistics'],
            $staff->tokens()->where('name', 'logistics-mobile')->first()->abilities
        );

        // The issued token is accepted by the scan surface.
        $this->withToken($response->json('token'))
            ->getJson('/api/logistics/scan/context')
            ->assertOk()
            ->assertJsonPath('account_type', 'logistics_staff')
            ->assertJsonPath('logistics.facility.id', $calamba->id);
    }

    public function test_inactive_logistics_staff_login_is_rejected(): void
    {
        $company = $this->company();
        $center = $this->center($company, 'Inactive Hub');
        $staff = $this->staff($company, $center, ['account_status' => 'suspended']);

        $this->postJson('/api/logistics/login', [
            'email' => $staff->email,
            'password' => 'secret123',
        ])->assertStatus(403);
    }

    public function test_platform_admin_login_is_read_only(): void
    {
        $admin = $this->platformAdmin();

        $response = $this->postJson('/api/logistics/login', [
            'email' => $admin->email,
            'password' => 'secret123',
        ]);

        $response->assertOk()
            ->assertJsonPath('account_type', 'platform_admin')
            ->assertJsonPath('logistics.can_scan', false)
            ->assertJsonPath('logistics.scope', 'global');

        $token = $response->json('token');

        $this->withToken($token)
            ->postJson('/api/logistics/scan/lookup', ['tracking_number' => 'TRK-20260101-ABCD'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'read_only');

        $this->withToken($token)
            ->postJson('/api/logistics/scan/confirm', ['tracking_number' => 'TRK-20260101-ABCD'])
            ->assertStatus(403)
            ->assertJsonPath('error', 'read_only');
    }

    public function test_designated_logistics_admin_is_global_and_can_scan(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $delivery = $this->delivery($calamba);
        $admin = $this->designatedLogisticsAdmin();

        Sanctum::actingAs($admin, ['logistics']);

        $this->getJson('/api/logistics/scan/context')
            ->assertOk()
            ->assertJsonPath('account_type', 'logistics_admin')
            ->assertJsonPath('logistics.scope', 'global')
            ->assertJsonPath('logistics.can_scan', true);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('already', false)
            ->assertJsonPath('facility.id', $calamba->id);

        $this->assertSame('scanned', $delivery->fresh()->parcel_status);
    }

    public function test_delivery_rider_login_still_returns_rider_surface(): void
    {
        $company = $this->company();
        $center = $this->center($company, 'Rider Hub');
        $riderUser = $this->riderUser($center);

        $this->postJson('/api/logistics/login', [
            'email' => $riderUser->email,
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('account_type', 'delivery_rider')
            ->assertJsonMissingPath('logistics');
    }

    public function test_hub_transport_driver_login_still_returns_courier_surface(): void
    {
        $company = $this->company();
        $driver = $this->driver($company);

        $this->postJson('/api/logistics/login', [
            'email' => $driver->email,
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('account_type', 'hub_transport_driver')
            ->assertJsonMissingPath('logistics');
    }

    // ---------------------------------------------------------------------
    // Scanning workflow
    // ---------------------------------------------------------------------

    public function test_calamba_staff_confirm_scans_parcel_at_own_facility(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $other = $this->center($company, 'Batangas Hub');
        $staff = $this->staff($company, $calamba);
        $delivery = $this->delivery($calamba);

        Sanctum::actingAs($staff, ['logistics']);

        // Read-only preview shows the parcel and its current hub.
        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('delivery.current_hub.id', $calamba->id)
            ->assertJsonPath('facility.id', $calamba->id);

        // A client-supplied facility is ignored; the account's hub wins.
        $response = $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
            'facility_id' => $other->id,
            'center_id' => $other->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('already', false)
            ->assertJsonPath('message', 'Parcel scanned successfully.')
            ->assertJsonPath('facility.id', $calamba->id)
            ->assertJsonPath('delivery.parcel_status', 'scanned')
            ->assertJsonPath('history.0.status', 'scanned');

        $fresh = $delivery->fresh();
        $this->assertSame('scanned', $fresh->parcel_status);
        $this->assertSame($calamba->id, (int) $fresh->center_id);
        $this->assertNotNull($fresh->scanned_at);

        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'scanned',
            'location' => $calamba->name,
            'changed_by' => $staff->id,
        ]);

        $this->assertSame(1, DeliveryStatusLog::where('delivery_id', $delivery->id)->count());
    }

    public function test_duplicate_confirm_is_idempotent_and_writes_one_log(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);
        $delivery = $this->delivery($calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])->assertOk()->assertJsonPath('already', false);

        $duplicate = $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ]);

        $duplicate->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('already', true)
            ->assertJsonPath('eligible', false);

        $this->assertSame(1, DeliveryStatusLog::where('delivery_id', $delivery->id)->count());
    }

    public function test_mobile_scan_is_visible_to_the_web_counter(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);
        $delivery = $this->delivery($calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])->assertOk()->assertJsonPath('already', false);

        // Same database, same parcel: the Logistics Web counter sees the
        // mobile scan and reports it as already scanned.
        $this->actingAs($staff)
            ->postJson('/logistics/deliveries/scan-verify', [
                'tracking_number' => $delivery->tracking_number,
            ])
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'already' => true,
            ]);

        $this->assertDatabaseHas('delivery_status_logs', [
            'delivery_id' => $delivery->id,
            'status' => 'scanned',
            'location' => $calamba->name,
            'changed_by' => $staff->id,
        ]);
        $this->assertSame(1, DeliveryStatusLog::where('delivery_id', $delivery->id)->count());
    }

    public function test_other_hub_staff_cannot_lookup_or_confirm(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $batangas = $this->center($company, 'Batangas Hub');
        $staff = $this->staff($company, $batangas);
        $delivery = $this->delivery($calamba);

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
        $this->assertSame(0, DeliveryStatusLog::where('delivery_id', $delivery->id)->count());
    }

    public function test_parcel_that_is_not_received_cannot_be_scanned(): void
    {
        $company = $this->company();
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
            ->assertJsonPath('error', 'invalid_state')
            ->assertJsonPath('eligible', false);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_state');

        $this->assertSame('pending_arrival', $delivery->fresh()->parcel_status);
        $this->assertSame(0, DeliveryStatusLog::where('delivery_id', $delivery->id)->count());
    }

    public function test_invalid_tracking_format_is_rejected(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/lookup', [
            'tracking_number' => 'NOT-A-TRACKING-NUMBER',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('tracking_number');
    }

    public function test_unknown_tracking_returns_not_found(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);

        Sanctum::actingAs($staff, ['logistics']);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => 'TRK-20260101-ZZZZ',
        ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_found');
    }

    // ---------------------------------------------------------------------
    // Token separation
    // ---------------------------------------------------------------------

    public function test_rider_token_cannot_reach_logistics_scan_endpoints(): void
    {
        $company = $this->company();
        $calamba = $this->center($company, 'Calamba Hub');
        $staff = $this->staff($company, $calamba);
        $delivery = $this->delivery($calamba);

        // A logistics account with a rider-ability token is still denied.
        Sanctum::actingAs($staff, ['rider']);

        $this->postJson('/api/logistics/scan/confirm', [
            'tracking_number' => $delivery->tracking_number,
        ])->assertStatus(403);

        // A rider account can never hold logistics authority.
        $riderUser = $this->riderUser($this->center($company, 'Rider Hub'));
        Sanctum::actingAs($riderUser, ['logistics']);

        $this->getJson('/api/logistics/scan/context')->assertStatus(403);

        $this->assertSame('received', $delivery->fresh()->parcel_status);
    }

    public function test_unauthenticated_scan_request_is_rejected(): void
    {
        $this->getJson('/api/logistics/scan/context')->assertStatus(401);
    }

    // ---------------------------------------------------------------------
    // Cross-role email uniqueness guard
    // ---------------------------------------------------------------------

    public function test_email_guard_rejects_logistics_rider_and_driver_conflicts(): void
    {
        $company = $this->company();
        $center = $this->center($company, 'Guard Hub');
        $staff = $this->staff($company, $center);
        $riderUser = $this->riderUser($center);
        $driver = $this->driver($company);

        $this->assertNotNull(AccountEmailGuard::conflict(strtoupper($staff->email)));
        $this->assertStringContainsString('Logistics account', AccountEmailGuard::conflict($staff->email));

        $this->assertStringContainsString('Delivery Rider account', AccountEmailGuard::conflict($riderUser->email));
        $this->assertStringContainsString('Hub Transport Driver account', AccountEmailGuard::conflict($driver->email));

        $this->assertNull(AccountEmailGuard::conflict('unused-'.bin2hex(random_bytes(4)).'@test.local'));
    }

    public function test_email_guard_ignores_the_accounts_own_record(): void
    {
        $company = $this->company();
        $center = $this->center($company, 'Self Hub');
        $staff = $this->staff($company, $center);

        $this->assertNull(AccountEmailGuard::conflict($staff->email, ['user_id' => $staff->id]));
        $this->assertNotNull(AccountEmailGuard::conflict($staff->email, ['user_id' => $staff->id + 1]));
    }

    public function test_email_guard_reserves_pending_application_email(): void
    {
        $email = 'pending-'.bin2hex(random_bytes(4)).'@test.local';

        RiderApplication::create([
            'name' => 'Pending Applicant',
            'email' => $email,
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'status' => 'pending',
            'application_type' => 'delivery_rider',
        ]);

        $this->assertTrue(AccountEmailGuard::hasPendingApplication(strtoupper($email)));

        $this->expectException(ValidationException::class);
        AccountEmailGuard::assertAvailable($email);
    }
}
