<?php

namespace Tests\Feature;

use App\Models\LogisticsCenter;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\ServiceArea;
use App\Models\User;
use App\Services\RiderAccountProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * License-expiry integration coverage.
 *
 * The merged rider profile / application flow captures a driver's-license
 * expiry date. These tests pin the whole vertical slice: the mobile apply
 * endpoint validates it, provisioning carries it onto the rider account, and
 * the profile + login payloads expose it (plus company / service area).
 *
 * Uses DatabaseTransactions; every row is rolled back. Protected by
 * Tests\TestCase::guardAgainstDestructiveDatabase().
 */
class RiderLicenseExpiryTest extends TestCase
{
    use DatabaseTransactions;

    private function center(): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => 'License Test Center',
            'address' => '1 Warehouse Rd',
            'city' => 'Quezon City',
            'is_active' => true,
        ]);
    }

    private function area(LogisticsCenter $center): ServiceArea
    {
        return ServiceArea::create([
            'logistics_center_id' => $center->id,
            'name' => 'Area A',
            'is_active' => true,
        ]);
    }

    private function application(array $extra = []): RiderApplication
    {
        return RiderApplication::create(array_merge([
            'name' => 'Juan Rider',
            'email' => 'juan.'.bin2hex(random_bytes(3)).'@test.local',
            'phone' => '0917'.random_int(1000000, 9999999),
            'vehicle_type' => 'motorcycle',
            'status' => 'pending',
        ], $extra));
    }

    private function riderUser(array $riderExtra = []): User
    {
        $email = 'rider.'.bin2hex(random_bytes(3)).'@test.local';

        $user = User::create([
            'name' => 'Test Rider',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
        ]);

        Rider::create(array_merge([
            'user_id' => $user->id,
            'name' => 'Test Rider',
            'email' => $email,
            'phone' => '09171234567',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
        ], $riderExtra));

        return $user->fresh();
    }

    // ------------------------------------------------ apply validation

    public function test_apply_requires_a_license_expiry(): void
    {
        $this->postJson('/api/logistics/rider/apply', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('license_expiry');
    }

    public function test_apply_rejects_a_past_license_expiry(): void
    {
        $this->postJson('/api/logistics/rider/apply', [
            'license_expiry' => now()->subDay()->format('Y-m-d'),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('license_expiry');
    }

    public function test_apply_accepts_a_future_license_expiry_and_advances_to_the_otp_gate(): void
    {
        // A future date clears the license rule, so the request falls through to
        // the server-side email-verification gate rather than failing validation
        // on `license_expiry`.
        $response = $this->postJson('/api/logistics/rider/apply', [
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ])->assertStatus(422);

        $this->assertArrayNotHasKey('license_expiry', $response->json('errors') ?? []);
    }

    // ------------------------------------------------ provisioning

    public function test_provisioner_copies_license_details_onto_the_rider(): void
    {
        $center = $this->center();
        $area = $this->area($center);

        $application = $this->application([
            'license_number' => 'N01-23-456789',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'vehicle_registration' => 'ABC-1234',
        ]);

        $admin = User::create([
            'name' => 'Admin', 'email' => 'admin.'.bin2hex(random_bytes(3)).'@test.local',
            'password' => Hash::make('x'), 'role' => 'admin', 'status' => 'active',
        ]);

        $rider = app(RiderAccountProvisioner::class)->approve($application, $admin, [
            'password' => 'Provisioned1!',
            'center_id' => $center->id,
            'service_area_id' => $area->id,
        ]);

        $this->assertSame('N01-23-456789', $rider->license_number);
        $this->assertSame('ABC-1234', $rider->vehicle_registration);
        $this->assertSame(
            now()->addYear()->format('Y-m-d'),
            $rider->license_expiry?->format('Y-m-d'),
            'the application license expiry must carry onto the provisioned rider'
        );
    }

    // ------------------------------------------------ profile + login payloads

    public function test_profile_payload_exposes_license_company_and_service_area(): void
    {
        $center = $this->center();
        $area = $this->area($center);

        $user = $this->riderUser([
            'center_id' => $center->id,
            'service_area_id' => $area->id,
            'license_number' => 'N01-23-456789',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'vehicle_registration' => 'ABC-1234',
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/logistics/rider/profile')
            ->assertOk()
            ->assertJsonPath('rider.license_number', 'N01-23-456789')
            ->assertJsonPath('rider.license_expiry', now()->addYear()->format('Y-m-d'))
            ->assertJsonPath('rider.vehicle_registration', 'ABC-1234')
            ->assertJsonPath('rider.company', 'License Test Center')
            ->assertJsonPath('rider.service_area', 'Area A');
    }

    public function test_login_payload_exposes_license_details(): void
    {
        $user = $this->riderUser([
            'license_number' => 'N01-23-456789',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'vehicle_registration' => 'ABC-1234',
        ]);

        $this->postJson('/api/logistics/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])
            ->assertOk()
            ->assertJsonPath('user.rider.license_number', 'N01-23-456789')
            ->assertJsonPath('user.rider.license_expiry', now()->addYear()->format('Y-m-d'))
            ->assertJsonPath('user.rider.vehicle_registration', 'ABC-1234');
    }

    // ------------------------------------------------ migration guard

    public function test_license_expiry_migration_up_is_a_noop_when_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('riders', 'license_expiry'));
        $this->assertTrue(Schema::hasColumn('rider_applications', 'license_expiry'));

        $migration = require __DIR__.'/../../database/migrations/2026_10_19_000004_add_license_expiry_to_riders_and_applications.php';

        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('riders', 'license_expiry'));
        $this->assertTrue(Schema::hasColumn('rider_applications', 'license_expiry'));
    }
}
