<?php

namespace Tests\Feature;

use App\Mail\RiderAccountApprovedMail;
use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\RiderEmailVerification;
use App\Models\ServiceArea;
use App\Models\User;
use App\Models\VehicleType;
use App\Services\HubTransferService;
use App\Services\HubTransportDriverProvisioner;
use App\Services\RiderAccountProvisioner;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class HubTransportDriverApplicationWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        VehicleType::firstOrCreate(['name' => 'motorcycle'], [
            'label' => 'Motorcycle',
            'capacity_kg' => 30.00,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        VehicleType::firstOrCreate(['name' => 'van'], [
            'label' => 'Van',
            'capacity_kg' => 800.00,
            'is_active' => true,
            'sort_order' => 2,
        ]);
        VehicleType::firstOrCreate(['name' => 'truck'], [
            'label' => 'Truck',
            'capacity_kg' => 2000.00,
            'is_active' => true,
            'sort_order' => 3,
        ]);
        VehicleType::firstOrCreate(['name' => 'l300'], [
            'label' => 'L300 / Utility Van',
            'capacity_kg' => 1000.00,
            'is_active' => true,
            'sort_order' => 4,
        ]);
        VehicleType::firstOrCreate(['name' => 'light_truck'], [
            'label' => 'Light Truck (4-Wheel)',
            'capacity_kg' => 2500.00,
            'is_active' => true,
            'sort_order' => 5,
        ]);
    }

    private function manager(): User
    {
        $manager = User::query()
            ->where('email', User::GLOBAL_LOGISTICS_ADMIN_EMAIL)
            ->where('role', 'logistics_admin')
            ->first();

        if ($manager) {
            return $manager;
        }

        $manager = new User([
            'phone' => '09171234567',
            'name' => 'Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'password' => 'TestManager123!',
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $manager->id = User::GLOBAL_LOGISTICS_ADMIN_ID;
        $manager->save();

        return $manager;
    }

    private function company(string $name = 'Test Logistics Co'): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => $name.' '.uniqid().' '.(++$this->seq),
            'status' => 'active',
        ]);
    }

    private function uniqueEmail(string $prefix = 'driver'): string
    {
        return $prefix.'_'.uniqid().'_'.(++$this->seq).'@test.local';
    }

    private function uniquePhone(): string
    {
        return '09'.rand(100000000, 999999999);
    }

    private function center(LogisticsCompany $company, string $name = 'Test Hub'): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name.' '.uniqid().' '.(++$this->seq),
            'address' => '100 Transport Way',
            'city' => 'Manila',
            'is_active' => true,
            'logistics_company_id' => $company->id,
        ]);
    }

    private function area(LogisticsCenter $center): ServiceArea
    {
        return ServiceArea::create([
            'name' => 'Hub Service Area '.(++$this->seq),
            'logistics_center_id' => $center->id,
            'is_active' => true,
        ]);
    }

    private function staffUser(LogisticsCompany $company, LogisticsCenter $center): User
    {
        return User::create([
            'phone' => '09171234567',
            'name' => 'Hub Staff '.(++$this->seq),
            'email' => $this->uniqueEmail('staff'),
            'password' => Hash::make('password123'),
            'role' => 'logistics_staff',
            'status' => 'active',
            'logistics_company_id' => $company->id,
            'center_id' => $center->id,
            'email_verified_at' => now(),
        ]);
    }

    private function hubDriverDocs(): array
    {
        return [
            'valid_id' => UploadedFile::fake()->create('id.jpg', 100, 'image/jpeg'),
            'drivers_license' => UploadedFile::fake()->create('license.jpg', 100, 'image/jpeg'),
            'other' => UploadedFile::fake()->create('clearance.pdf', 100, 'application/pdf'),
        ];
    }

    private function approvedHubApplication(LogisticsCompany $company, LogisticsCenter $center, string $email): RiderApplication
    {
        $application = RiderApplication::create([
            'name' => 'Hub Driver Resend '.(++$this->seq),
            'email' => $email,
            'phone' => $this->uniquePhone(),
            'birthday' => '1991-02-02',
            'sex' => 'male',
            'address' => 'Manila',
            'application_type' => 'hub_transport_driver',
            'license_number' => 'N09-'.rand(100000, 999999),
            'license_expiry' => now()->addYears(2),
            'status' => 'pending',
            'submitted_via' => 'mobile',
        ]);

        app(HubTransportDriverProvisioner::class)->approve($application, $this->manager(), [
            'center_id' => $center->id,
            'password' => 'DriverPass123!',
        ]);

        return $application->fresh();
    }

    public function test_vehicle_types_returns_two_wheel_by_default_and_four_wheel_when_requested(): void
    {
        // Default (backward-compatible) returns two-wheel
        $respDefault = $this->getJson('/api/logistics/vehicle-types');
        $respDefault->assertOk();
        $typesDefault = collect($respDefault->json('vehicle_types'));
        $this->assertTrue($typesDefault->contains('name', 'motorcycle'));
        $this->assertFalse($typesDefault->contains('name', 'truck'));

        // Hub Transport Driver request returns four-wheel (van, truck, l300, light_truck)
        $respHub = $this->getJson('/api/logistics/vehicle-types?application_type=hub_transport_driver');
        $respHub->assertOk();
        $typesHub = collect($respHub->json('vehicle_types'));
        $this->assertTrue($typesHub->contains('name', 'truck'));
        $this->assertTrue($typesHub->contains('name', 'van'));
        $this->assertTrue($typesHub->contains('name', 'l300'));
        $this->assertFalse($typesHub->contains('name', 'motorcycle'));
    }

    public function test_hub_transport_driver_application_submission_does_not_require_motorcycle_or_truck_fields(): void
    {
        $email = $this->uniqueEmail('hubdriver');
        RiderEmailVerification::create([
            'email' => $email,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
        ]);

        $licenseNum = 'N01-99-'.rand(100000, 999999);

        // Applicant submits driver information, license, and valid ID without vehicle/truck fields
        $resp = $this->postJson('/api/logistics/rider/apply', [
            'name' => 'Carlos Hub Driver',
            'email' => $email,
            'phone' => $this->uniquePhone(),
            'birthday' => '1995-05-15',
            'sex' => 'male',
            'address' => 'Brgy Central, Manila',
            'application_type' => 'hub_transport_driver',
            'license_number' => $licenseNum,
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'accepted_terms' => '1',
            'documents' => $this->hubDriverDocs(),
        ]);

        $resp->assertStatus(201);

        $this->assertDatabaseHas('rider_applications', [
            'email' => $email,
            'application_type' => 'hub_transport_driver',
            'license_number' => $licenseNum,
            'vehicle_type' => null,
            'license_plate' => null,
            'vehicle_registration' => null,
            'vehicle_name' => null,
            'vehicle_model' => null,
            'parcel_capacity' => null,
            'capacity_kg' => null,
            'status' => 'pending',
        ]);

        // Permanent records must NOT be created before admin approval
        $this->assertDatabaseMissing('hub_transport_drivers', ['email' => $email]);
    }

    public function test_application_status_returns_application_type_and_label(): void
    {
        $app = RiderApplication::create([
            'name' => 'Driver Status Test',
            'email' => $this->uniqueEmail('driver-status'),
            'phone' => $this->uniquePhone(),
            'birthday' => '1992-04-10',
            'sex' => 'male',
            'address' => 'Cebu City',
            'application_type' => 'hub_transport_driver',
            'license_number' => 'N02-88-'.rand(100000, 999999),
            'license_expiry' => now()->addYear(),
            'status' => 'pending',
            'submitted_via' => 'mobile',
        ]);

        $token = \App\Support\ApplicationStatusAccess::grant(request(), $app->email);
        $response = $this->withHeaders(['X-Application-Access' => $token])
            ->getJson('/api/logistics/rider/application-status?email='.$app->email);
        $response->assertOk()
            ->assertJsonPath('application.application_type', 'hub_transport_driver')
            ->assertJsonPath('application.application_type_label', 'Hub Transport Driver');
    }

    public function test_admin_approving_hub_transport_driver_creates_driver_without_auto_assigning_truck(): void
    {
        $manager = $this->manager();
        $company = $this->company();
        $center = $this->center($company);

        $licenseNumber = 'N03-77-'.rand(100000, 999999);
        $app = RiderApplication::create([
            'name' => 'Transport Driver One',
            'email' => $this->uniqueEmail('driver-one'),
            'phone' => $this->uniquePhone(),
            'birthday' => '1990-01-01',
            'sex' => 'male',
            'address' => 'Quezon City',
            'application_type' => 'hub_transport_driver',
            'license_number' => $licenseNumber,
            'license_expiry' => now()->addYear(),
            'status' => 'pending',
            'submitted_via' => 'mobile',
        ]);

        $response = $this->actingAs($manager)
            ->post(route('rider-applications.approve', $app), [
                'center_id' => $center->id,
                'password' => 'DriverPass123!',
                'password_confirmation' => 'DriverPass123!',
                'notes' => 'Driver approved for north inter-hub route.',
            ]);

        $response->assertRedirect(route('rider-applications.show', $app))
            ->assertSessionHas('success');

        // 1. Verify application status and references
        $freshApp = $app->fresh();
        $this->assertSame('approved', $freshApp->status);
        $this->assertNotNull($freshApp->provisioned_at);
        $this->assertSame($center->id, $freshApp->center_id);
        $this->assertNotNull($freshApp->hub_transport_driver_id);
        $this->assertNull($freshApp->logistics_truck_id); // Truck is NOT auto-assigned

        // 2. Verify HubTransportDriver created atomically linked to company and user
        $driver = HubTransportDriver::where('email', $app->email)->first();
        $this->assertNotNull($driver);
        $this->assertSame($company->id, $driver->logistics_company_id);
        $this->assertSame('available', $driver->status);
        $this->assertNull($driver->logistics_truck_id); // Truck NOT automatically assigned
        $this->assertFalse($driver->is_online);

        // 3. Verify user account provisioned
        $user = User::where('email', $app->email)->first();
        $this->assertNotNull($user);
        $this->assertSame('rider', $user->role);
        $this->assertSame('active', $user->status);
        $this->assertSame($app->phone, $user->phone);
        $this->assertSame($user->id, $driver->user_id);

        // 4. Verify no delivery rider or truck record auto-created
        $this->assertDatabaseMissing('riders', ['email' => $app->email]);

        // 5. Verify Fleet Management displays approved driver awaiting truck
        $fleetResp = $this->actingAs($manager)->get(route('fleet.index', ['logistics_company_id' => $company->id]));
        $fleetResp->assertOk();
        $fleetResp->assertSee($driver->name);
        $fleetResp->assertSee('Unassigned');

        // 6. Verify approval email was sent
        Mail::assertSent(RiderAccountApprovedMail::class, function ($mail) use ($app) {
            return $mail->application->id === $app->id;
        });
    }

    public function test_fleet_management_assigns_available_four_wheel_truck_to_hub_driver(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        // 4-wheel truck owned by company
        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'VAN-'.rand(1000, 9999),
            'vehicle_type' => 'van',
            'name' => 'Company Van 01',
            'parcel_capacity' => 150,
            'capacity_kg' => 1200.0,
            'status' => 'available',
        ]);

        // Approved driver without truck
        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Unassigned Hub Driver',
            'email' => 'driver-unassigned'.(++$this->seq).'@test.local',
            'password' => Hash::make('password123'),
            'license_number' => 'N04-55-'.rand(100000, 999999),
            'status' => 'available',
            'logistics_truck_id' => null,
        ]);

        // Admin assigns the 4-wheel truck to the driver
        $assignResp = $this->actingAs($manager)->put(route('fleet.drivers.update', $driver), [
            'status' => 'available',
            'logistics_truck_id' => $truck->id,
            'remarks' => 'Assigned to North Route',
        ]);

        $assignResp->assertRedirect(route('fleet.index'))->assertSessionHas('success');

        $driver->refresh();
        $truck->refresh();

        $this->assertSame($truck->id, $driver->logistics_truck_id);
        $this->assertSame('assigned', $truck->status);
    }

    public function test_fleet_management_rejects_assigning_motorcycle_to_hub_transport_driver(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        // 2-wheel vehicle (motorcycle)
        $motorcycle = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'MC-'.rand(1000, 9999),
            'vehicle_type' => 'motorcycle',
            'parcel_capacity' => 30,
            'capacity_kg' => 30.0,
            'status' => 'available',
        ]);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Hub Driver',
            'email' => 'driver-mc-test'.(++$this->seq).'@test.local',
            'password' => Hash::make('password123'),
            'license_number' => 'N04-55-'.rand(100000, 999999),
            'status' => 'available',
            'logistics_truck_id' => null,
        ]);

        // Admin attempts to assign motorcycle to Hub Transport Driver - must fail with 422
        $assignResp = $this->actingAs($manager)->put(route('fleet.drivers.update', $driver), [
            'status' => 'available',
            'logistics_truck_id' => $motorcycle->id,
        ]);

        $assignResp->assertStatus(422);
        $this->assertNull($driver->fresh()->logistics_truck_id);
    }

    public function test_fleet_management_rejects_assigning_already_assigned_truck_to_second_driver(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'L300-'.rand(1000, 9999),
            'vehicle_type' => 'l300',
            'parcel_capacity' => 120,
            'capacity_kg' => 1000.0,
            'status' => 'assigned',
        ]);

        $driver1 = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'First Driver',
            'email' => 'driver1'.(++$this->seq).'@test.local',
            'password' => Hash::make('password123'),
            'status' => 'available',
            'logistics_truck_id' => $truck->id,
        ]);

        $driver2 = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Second Driver',
            'email' => 'driver2'.(++$this->seq).'@test.local',
            'password' => Hash::make('password123'),
            'status' => 'available',
            'logistics_truck_id' => null,
        ]);

        // Attempt to assign already-assigned truck to second driver
        $assignResp = $this->actingAs($manager)->put(route('fleet.drivers.update', $driver2), [
            'status' => 'available',
            'logistics_truck_id' => $truck->id,
        ]);

        $assignResp->assertStatus(422);
        $this->assertNull($driver2->fresh()->logistics_truck_id);
    }

    public function test_fleet_management_can_safely_unassign_truck_restoring_truck_availability(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TRK-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 200,
            'capacity_kg' => 2500.0,
            'status' => 'assigned',
        ]);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Assigned Driver',
            'email' => 'driver-unassign'.(++$this->seq).'@test.local',
            'password' => Hash::make('password123'),
            'status' => 'available',
            'logistics_truck_id' => $truck->id,
        ]);

        // Unassign truck
        $unassignResp = $this->actingAs($manager)->put(route('fleet.drivers.update', $driver), [
            'status' => 'available',
            'logistics_truck_id' => null,
        ]);

        $unassignResp->assertRedirect(route('fleet.index'))->assertSessionHas('success');

        $driver->refresh();
        $truck->refresh();

        $this->assertNull($driver->logistics_truck_id);
        $this->assertSame('available', $truck->status);
    }

    public function test_repeated_approval_is_idempotent_and_never_overwrites_existing_password(): void
    {
        $manager = $this->manager();
        $company = $this->company();
        $center = $this->center($company);

        $email = 'idempotent-driver'.(++$this->seq).'@test.local';

        $app = RiderApplication::create([
            'name' => 'Existing Driver Updated',
            'email' => $email,
            'phone' => '09170000005',
            'birthday' => '1990-01-01',
            'sex' => 'male',
            'address' => 'Pasig City',
            'application_type' => 'hub_transport_driver',
            'license_number' => 'N04-66-999999',
            'license_expiry' => now()->addYear(),
            'status' => 'pending',
            'submitted_via' => 'mobile',
        ]);

        // First approval
        $this->actingAs($manager)
            ->post(route('rider-applications.approve', $app), [
                'center_id' => $center->id,
                'password' => 'FirstPass123!',
                'password_confirmation' => 'FirstPass123!',
            ])
            ->assertRedirect();

        $driver = HubTransportDriver::where('email', $email)->first();
        $this->assertNotNull($driver);
        $initialPasswordHash = $driver->password;

        // Repeated approval on the already approved application
        $this->actingAs($manager)
            ->post(route('rider-applications.approve', $app), [
                'center_id' => $center->id,
                'password' => 'NewIgnoredPass123!',
                'password_confirmation' => 'NewIgnoredPass123!',
            ])
            ->assertRedirect();

        // Must still be only 1 driver record
        $this->assertSame(1, HubTransportDriver::where('email', $email)->count());

        // Password hash must remain exactly identical (never overwritten)
        $this->assertSame($initialPasswordHash, $driver->fresh()->password);
    }

    public function test_resend_hub_approval_email_keeps_password_and_does_not_duplicate_accounts(): void
    {
        $company = $this->company();
        $center = $this->center($company);
        $email = $this->uniqueEmail('resend-approved');
        $app = $this->approvedHubApplication($company, $center, $email);

        $driver = HubTransportDriver::where('email', $email)->firstOrFail();
        $user = User::where('email', $email)->firstOrFail();
        $driverHash = $driver->password;
        $userHash = $user->password;

        $response = $this->actingAs($this->manager())
            ->post(route('rider-applications.resend-credentials', $app));

        $response->assertRedirect(route('rider-applications.show', $app))
            ->assertSessionHas('success', fn ($message) => str_contains($message, 'Approval notice resent')
                && str_contains($message, 'Existing password remains unchanged'));

        // Approval mail + resent mail; the resent notice must not include a password.
        Mail::assertSent(RiderAccountApprovedMail::class, 2);
        Mail::assertSent(RiderAccountApprovedMail::class, fn ($mail) => $mail->temporaryPassword === null
            && $mail->hasTo($email));

        $freshApp = $app->fresh();
        $this->assertSame('approved', $freshApp->status);
        $this->assertNotNull($freshApp->provisioned_at);
        $this->assertSame($driver->id, $freshApp->hub_transport_driver_id);
        $this->assertSame($driverHash, HubTransportDriver::whereKey($driver->id)->value('password'));
        $this->assertSame($userHash, User::whereKey($user->id)->value('password'));
        $this->assertSame(1, HubTransportDriver::where('email', $email)->count());
        $this->assertSame(1, User::where('email', $email)->count());
        $this->assertSame(1, RiderApplication::where('email', $email)->count());
    }

    public function test_repeated_resend_clicks_resend_without_creating_duplicates(): void
    {
        $company = $this->company();
        $center = $this->center($company);
        $email = $this->uniqueEmail('resend-repeat');
        $app = $this->approvedHubApplication($company, $center, $email);

        $driver = HubTransportDriver::where('email', $email)->firstOrFail();
        $driverHash = $driver->password;

        $manager = $this->manager();
        $this->actingAs($manager)->post(route('rider-applications.resend-credentials', $app))
            ->assertRedirect()->assertSessionHas('success');
        $this->actingAs($manager)->post(route('rider-applications.resend-credentials', $app))
            ->assertRedirect()->assertSessionHas('success');

        Mail::assertSent(RiderAccountApprovedMail::class, 3);
        $this->assertSame($driverHash, HubTransportDriver::whereKey($driver->id)->value('password'));
        $this->assertSame(1, HubTransportDriver::where('email', $email)->count());
        $this->assertSame(1, User::where('email', $email)->count());
        $this->assertSame(1, RiderApplication::where('email', $email)->count());
        $this->assertSame('approved', $app->fresh()->status);
    }

    public function test_resend_hub_approval_email_rejects_pending_application(): void
    {
        $app = RiderApplication::create([
            'name' => 'Pending Hub Driver',
            'email' => $this->uniqueEmail('resend-pending'),
            'phone' => $this->uniquePhone(),
            'birthday' => '1993-03-03',
            'sex' => 'male',
            'address' => 'Makati',
            'application_type' => 'hub_transport_driver',
            'license_number' => 'N10-'.rand(100000, 999999),
            'license_expiry' => now()->addYear(),
            'status' => 'pending',
            'submitted_via' => 'mobile',
        ]);

        $this->actingAs($this->manager())
            ->post(route('rider-applications.resend-credentials', $app))
            ->assertStatus(422);

        Mail::assertNothingSent();
        $this->assertSame('pending', $app->fresh()->status);
    }

    public function test_resend_hub_approval_email_without_valid_recipient_warns_and_sends_nothing(): void
    {
        $company = $this->company();
        $center = $this->center($company);
        $email = $this->uniqueEmail('resend-invalid');
        $app = $this->approvedHubApplication($company, $center, $email);
        $driver = HubTransportDriver::where('email', $email)->firstOrFail();

        // Simulate a legacy record whose stored email can no longer deliver.
        DB::table('rider_applications')->where('id', $app->id)->update(['email' => 'not-an-email']);
        DB::table('hub_transport_drivers')->where('id', $driver->id)->update(['email' => '']);

        $this->actingAs($this->manager())
            ->post(route('rider-applications.resend-credentials', $app))
            ->assertRedirect(route('rider-applications.show', $app))
            ->assertSessionHas('warning', fn ($message) => str_contains($message, 'no valid email address'));

        // Only the original approval email was sent.
        Mail::assertSent(RiderAccountApprovedMail::class, 1);
        $this->assertSame(1, HubTransportDriver::whereKey($driver->id)->count());
        $this->assertSame('approved', $app->fresh()->status);
    }

    public function test_failed_hub_approval_resend_preserves_password_and_warns(): void
    {
        $company = $this->company();
        $center = $this->center($company);
        $email = $this->uniqueEmail('resend-failure');
        $app = $this->approvedHubApplication($company, $center, $email);
        $driver = HubTransportDriver::where('email', $email)->firstOrFail();
        $driverHash = $driver->password;

        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable during test'));

        $this->actingAs($this->manager())
            ->post(route('rider-applications.resend-credentials', $app))
            ->assertRedirect(route('rider-applications.show', $app))
            ->assertSessionHas('warning', fn ($message) => str_contains($message, 'could not be sent'));

        $this->assertSame($driverHash, HubTransportDriver::whereKey($driver->id)->value('password'));
        $this->assertSame(1, HubTransportDriver::where('email', $email)->count());
        $this->assertSame(1, User::where('email', $email)->count());
        $this->assertSame('approved', $app->fresh()->status);
    }

    public function test_delivery_rider_regression_protection(): void
    {
        $manager = $this->manager();
        $company = $this->company();
        $center = $this->center($company);
        $area = $this->area($center);

        $riderEmail = 'standard-rider'.(++$this->seq).'@test.local';
        $app = RiderApplication::create([
            'name' => 'Standard Delivery Rider',
            'email' => $riderEmail,
            'phone' => '09170000009',
            'birthday' => '1994-06-20',
            'sex' => 'male',
            'address' => 'Pasay City',
            'application_type' => 'delivery_rider',
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'MC-'.rand(1000, 9999),
            'license_number' => 'N08-'.rand(100000, 999999),
            'license_expiry' => now()->addYear(),
            'vehicle_registration' => 'REG-MC-1',
            'status' => 'pending',
            'submitted_via' => 'mobile',
        ]);

        $provisioner = app(RiderAccountProvisioner::class);
        $rider = $provisioner->approve($app, $manager, [
            'center_id' => $center->id,
            'service_area_id' => $area->id,
            'password' => 'RiderPass123!',
        ]);

        $this->assertInstanceOf(Rider::class, $rider);
        $this->assertSame($center->id, $rider->center_id);
        $this->assertSame($area->id, $rider->service_area_id);

        // Created User with role rider
        $user = User::where('email', $riderEmail)->first();
        $this->assertNotNull($user);
        $this->assertSame('rider', $user->role);

        // Never created HubTransportDriver or LogisticsTruck
        $this->assertDatabaseMissing('hub_transport_drivers', ['email' => $riderEmail]);

        // Rider can log into rider mobile API
        $riderLogin = $this->postJson('/api/logistics/login', [
            'email' => $riderEmail,
            'password' => 'RiderPass123!',
        ]);
        $riderLogin->assertOk();
    }

    public function test_hub_transfer_permissions_enforced_in_backend(): void
    {
        $manager = $this->manager();
        $company = $this->company();
        $originCenter = $this->center($company, 'Origin Hub');
        $destCenter = $this->center($company, 'Destination Hub');
        $unrelatedCenter = $this->center($company, 'Unrelated Hub');

        $staffOrigin = $this->staffUser($company, $originCenter);
        $staffDest = $this->staffUser($company, $destCenter);
        $staffUnrelated = $this->staffUser($company, $unrelatedCenter);

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TCK-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'status' => 'available',
        ]);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Assigned Transport Driver',
            'email' => 'assigned-driver'.(++$this->seq).'@test.local',
            'phone' => '09170000010',
            'password' => Hash::make('password123'),
            'logistics_truck_id' => $truck->id,
            'status' => 'available',
        ]);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-'.rand(100000, 999999),
            'center_id' => $originCenter->id,
            'destination_center_id' => $destCenter->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'sorted',
            'weight' => 5.0,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '09170000011',
            'recipient_address' => 'Dest Address',
        ]);

        // Unrelated staff cannot create transfer for origin hub
        $unrelatedResp = $this->actingAs($staffUnrelated)->post(route('hub-transfers.store'), [
            'origin_center_id' => $originCenter->id,
            'destination_center_id' => $destCenter->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$delivery->id],
        ]);
        $unrelatedResp->assertForbidden();

        // Origin staff CAN create transfer
        $originResp = $this->actingAs($staffOrigin)->post(route('hub-transfers.store'), [
            'origin_center_id' => $originCenter->id,
            'destination_center_id' => $destCenter->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$delivery->id],
        ]);
        $originResp->assertRedirect();

        $transfer = HubTransfer::where('origin_center_id', $originCenter->id)->latest('id')->first();
        $this->assertNotNull($transfer);

        // Global admin can view across all hubs
        $adminView = $this->actingAs($manager)->get(route('hub-transfers.show', $transfer));
        $adminView->assertOk();

        // Origin staff loads parcel
        $this->actingAs($staffOrigin)->post(route('hub-transfers.load', $transfer), [
            'tracking_number' => $delivery->tracking_number,
        ])->assertRedirect();

        // Driver comes online and performs dispatch
        $driver->update(['is_online' => true]);
        $transfer = $transfer->fresh();
        HubTransferService::dispatch($transfer, null, $driver);

        // Arrive at destination recorded by destination staff
        $transfer = $transfer->fresh();
        $this->actingAs($staffDest)->post(route('hub-transfers.arrive', $transfer))->assertRedirect();

        // Origin staff CANNOT receive arriving parcels at destination (only destination staff)
        $originReceiveResp = $this->actingAs($staffOrigin)->post(route('hub-transfers.receive', $transfer), [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ]);
        $originReceiveResp->assertForbidden();

        // Destination hub staff CAN receive arriving parcels
        $destReceiveResp = $this->actingAs($staffDest)->post(route('hub-transfers.receive', $transfer), [
            'tracking_number' => $delivery->tracking_number,
            'condition' => 'good',
        ]);
        $destReceiveResp->assertRedirect()->assertSessionHas('success');
    }

    public function test_01_admin_registers_new_company_truck(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $plate = 'TRK-'.rand(1000, 9999);
        $resp = $this->actingAs($manager)->post(route('fleet.trucks.store'), [
            'logistics_company_id' => $company->id,
            'plate_number' => $plate,
            'vehicle_type' => 'light truck',
            'model' => 'Isuzu Elf 4-Wheel',
            'parcel_capacity' => 500,
            'capacity_kg' => 2500.00,
            'ownership_type' => 'company',
            'remarks' => 'Newly acquired company truck.',
        ]);

        $resp->assertRedirect()->assertSessionHas('success');
        $this->assertDatabaseHas('logistics_trucks', [
            'logistics_company_id' => $company->id,
            'plate_number' => $plate,
            'ownership_type' => 'company',
            'status' => 'available',
            'verification_status' => 'approved',
        ]);
    }

    public function test_02_admin_edits_existing_truck(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TCK-'.rand(1000, 9999),
            'vehicle_type' => 'van',
            'model' => 'Toyota HiAce',
            'parcel_capacity' => 200,
            'capacity_kg' => 1000,
            'ownership_type' => 'company',
            'status' => 'available',
        ]);

        $resp = $this->actingAs($manager)->put(route('fleet.trucks.update', $truck), [
            'vehicle_type' => 'light truck',
            'model' => 'Toyota HiAce High Roof',
            'parcel_capacity' => 300,
            'capacity_kg' => 1500,
            'status' => 'maintenance',
            'remarks' => 'Scheduled 20k PMS checkup.',
        ]);

        $resp->assertRedirect()->assertSessionHas('success');
        $truck = $truck->fresh();
        $this->assertSame('light truck', $truck->vehicle_type);
        $this->assertSame(300, $truck->parcel_capacity);
        $this->assertSame('maintenance', $truck->status);
        $this->assertSame('Scheduled 20k PMS checkup.', $truck->remarks);
    }

    public function test_03_admin_assigns_and_unassigns_approved_hub_transport_driver(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TCK-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 400,
            'capacity_kg' => 2000,
            'ownership_type' => 'company',
            'status' => 'available',
            'verification_status' => 'approved',
        ]);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Fleet Driver Test',
            'email' => 'driver-fleet'.(++$this->seq).'@test.local',
            'phone' => '09170000021',
            'password' => Hash::make('password123'),
            'logistics_truck_id' => null,
            'status' => 'available',
        ]);

        // Assign truck
        $this->actingAs($manager)->put(route('fleet.drivers.update', $driver), [
            'status' => 'available',
            'logistics_truck_id' => $truck->id,
        ])->assertRedirect()->assertSessionHas('success');

        $driver = $driver->fresh();
        $truck = $truck->fresh();
        $this->assertSame($truck->id, $driver->logistics_truck_id);
        $this->assertSame('assigned', $truck->status);

        // Unassign truck
        $this->actingAs($manager)->put(route('fleet.drivers.update', $driver), [
            'status' => 'available',
            'logistics_truck_id' => null,
        ])->assertRedirect()->assertSessionHas('success');

        $driver = $driver->fresh();
        $truck = $truck->fresh();
        $this->assertNull($driver->logistics_truck_id);
        $this->assertSame('available', $truck->status);
    }

    public function test_04_damaged_truck_cannot_receive_new_transfer(): void
    {
        $company = $this->company();
        $originCenter = $this->center($company);
        $destCenter = $this->center($company);
        $staff = $this->staffUser($company, $originCenter);

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TCK-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'status' => 'damaged',
            'verification_status' => 'approved',
        ]);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Driver Damaged Test',
            'email' => 'driver-dmg'.(++$this->seq).'@test.local',
            'phone' => '09170000022',
            'password' => Hash::make('password123'),
            'logistics_truck_id' => $truck->id,
            'status' => 'available',
        ]);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-'.rand(100000, 999999),
            'center_id' => $originCenter->id,
            'destination_center_id' => $destCenter->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'sorted',
            'weight' => 5.0,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '09170000023',
            'recipient_address' => 'Dest Address',
        ]);

        $resp = $this->actingAs($staff)->postJson(route('hub-transfers.store'), [
            'origin_center_id' => $originCenter->id,
            'destination_center_id' => $destCenter->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$delivery->id],
        ]);

        $resp->assertStatus(422);
    }

    public function test_05_repaired_truck_can_become_available_again(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TCK-'.rand(1000, 9999),
            'vehicle_type' => 'van',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'status' => 'damaged',
            'remarks' => 'Front bumper cracked',
        ]);

        $this->assertFalse($truck->isAssignable());

        // Manager marks repaired back to available
        $this->actingAs($manager)->put(route('fleet.trucks.update', $truck), [
            'status' => 'available',
            'remarks' => 'Repairs completed and inspected.',
        ])->assertRedirect()->assertSessionHas('success');

        $truck = $truck->fresh();
        $this->assertSame('available', $truck->status);
        $this->assertTrue($truck->isAssignable());
    }

    public function test_06_truck_marked_beyond_repair_retired_cannot_be_assigned(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TCK-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 200,
            'capacity_kg' => 2000,
            'status' => 'available',
        ]);

        // Retire the truck
        $this->actingAs($manager)->put(route('fleet.trucks.update', $truck), [
            'status' => 'retired',
            'remarks' => 'Decommissioned beyond economic repair.',
        ])->assertRedirect();

        $truck = $truck->fresh();
        $this->assertTrue($truck->isRetired());
        $this->assertFalse($truck->isAssignable());

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Driver Retired Test',
            'email' => 'driver-ret'.(++$this->seq).'@test.local',
            'phone' => '09170000024',
            'password' => Hash::make('password123'),
            'logistics_truck_id' => null,
            'status' => 'available',
        ]);

        // Attempting to assign retired truck to driver fails
        $resp = $this->actingAs($manager)->put(route('fleet.drivers.update', $driver), [
            'status' => 'available',
            'logistics_truck_id' => $truck->id,
        ]);
        $resp->assertStatus(422);
    }

    public function test_07_retired_truck_history_and_previous_trip_records_remain_accessible(): void
    {
        $manager = $this->manager();
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TCK-HIST-'.rand(100, 999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'status' => 'retired',
        ]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-HIST-001',
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'truck_id' => $truck->id,
            'status' => 'received',
            'created_by' => $manager->id,
        ]);

        // Retired truck remains in database and is never deleted
        $this->assertDatabaseHas('logistics_trucks', ['id' => $truck->id]);
        $this->assertSame(1, $truck->transfers()->count());
        $this->assertSame($transfer->id, $truck->transfers->first()->id);
    }

    public function test_08_driver_submits_driver_owned_truck_application(): void
    {
        $email = $this->uniqueEmail('driverowned');
        RiderEmailVerification::create([
            'email' => $email,
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'consumed_at' => now(),
        ]);

        $licenseNum = 'N01-77-'.rand(100000, 999999);
        $plate = 'OWN-'.rand(1000, 9999);

        $docs = $this->hubDriverDocs();
        $docs['vehicle_registration'] = UploadedFile::fake()->create('or_cr.jpg', 100, 'image/jpeg');

        $resp = $this->postJson('/api/logistics/rider/apply', [
            'name' => 'Eduardo Driver Owned',
            'email' => $email,
            'phone' => $this->uniquePhone(),
            'birthday' => '1990-03-20',
            'sex' => 'male',
            'address' => 'Quezon City',
            'application_type' => 'hub_transport_driver',
            'vehicle_ownership' => 'driver_owned',
            'vehicle_type' => 'light truck',
            'license_plate' => $plate,
            'vehicle_registration' => 'REG-OWN-1234',
            'vehicle_model' => 'Mitsubishi Canter',
            'parcel_capacity' => 450,
            'capacity_kg' => 2000.00,
            'license_number' => $licenseNum,
            'license_expiry' => now()->addYear()->format('Y-m-d'),
            'accepted_terms' => '1',
            'documents' => $docs,
        ]);

        $resp->assertStatus(201);

        $this->assertDatabaseHas('rider_applications', [
            'email' => $email,
            'application_type' => 'hub_transport_driver',
            'vehicle_ownership' => 'driver_owned',
            'license_plate' => $plate,
            'vehicle_registration' => 'REG-OWN-1234',
        ]);
    }

    public function test_09_admin_approves_and_rejects_driver_owned_vehicles(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Owner Driver Test',
            'email' => 'driver-appr'.(++$this->seq).'@test.local',
            'phone' => '09170000026',
            'password' => Hash::make('password123'),
            'status' => 'available',
        ]);

        // Truck pending review
        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'REV-'.rand(1000, 9999),
            'vehicle_type' => 'van',
            'parcel_capacity' => 200,
            'capacity_kg' => 1000,
            'ownership_type' => 'driver_owned',
            'hub_transport_driver_id' => $driver->id,
            'status' => 'available',
            'verification_status' => 'pending_review',
        ]);

        $this->assertTrue($truck->isPendingReview());
        $this->assertFalse($truck->isAssignable());

        // Admin approves
        $this->actingAs($manager)->post(route('fleet.trucks.approve', $truck), [
            'notes' => 'OR/CR verified and active.',
        ])->assertRedirect()->assertSessionHas('success');

        $truck = $truck->fresh();
        $this->assertTrue($truck->isApproved());
        $this->assertTrue($truck->isAssignable());

        // Admin rejects another vehicle
        $rejectTruck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'REJ-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 200,
            'capacity_kg' => 1000,
            'ownership_type' => 'driver_owned',
            'hub_transport_driver_id' => $driver->id,
            'status' => 'available',
            'verification_status' => 'pending_review',
        ]);

        $this->actingAs($manager)->post(route('fleet.trucks.reject', $rejectTruck), [
            'reason' => 'Expired registration certificate.',
        ])->assertRedirect()->assertSessionHas('success');

        $rejectTruck = $rejectTruck->fresh();
        $this->assertTrue($rejectTruck->isRejected());
        $this->assertFalse($rejectTruck->isAssignable());
    }

    public function test_10_unapproved_driver_owned_vehicle_cannot_be_assigned_to_transfers(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);
        $staff = $this->staffUser($company, $origin);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Pending Driver Test',
            'email' => 'driver-pnd'.(++$this->seq).'@test.local',
            'phone' => '09170000027',
            'password' => Hash::make('password123'),
            'status' => 'available',
        ]);

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'PND-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'ownership_type' => 'driver_owned',
            'hub_transport_driver_id' => $driver->id,
            'status' => 'available',
            'verification_status' => 'pending_review',
        ]);
        $driver->update(['logistics_truck_id' => $truck->id]);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-'.rand(100000, 999999),
            'center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'sorted',
            'weight' => 5.0,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '09170000028',
            'recipient_address' => 'Dest Address',
        ]);

        $resp = $this->actingAs($staff)->post(route('hub-transfers.store'), [
            'origin_center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'parcels' => [$delivery->id],
        ]);

        $resp->assertStatus(422);
    }

    public function test_11_company_provided_vehicle_receives_standard_trip_compensation(): void
    {
        $manager = $this->manager();
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'COM-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'ownership_type' => 'company',
            'status' => 'available',
            'verification_status' => 'approved',
        ]);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Standard Pay Driver',
            'email' => $this->uniqueEmail('driver-com'),
            'phone' => $this->uniquePhone(),
            'password' => Hash::make('password123'),
            'logistics_truck_id' => $truck->id,
            'status' => 'available',
        ]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-COM-'.rand(100, 999),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'received',
            'created_by' => $manager->id,
        ]);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-'.rand(100000, 999999),
            'center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'weight' => 5.0,
            'recipient_name' => 'Recipient',
            'recipient_phone' => $this->uniquePhone(),
            'recipient_address' => 'Dest Address',
        ]);

        \App\Support\EscrowEarnings::creditInterHubDriverOnTransferReceipt($transfer, $delivery);

        $earning = \App\Models\HubTransferEarning::where('hub_transfer_id', $transfer->id)
            ->where('hub_transport_driver_id', $driver->id)
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals(20.00, (float) $earning->base_amount);
        $this->assertEquals(0.00, (float) $earning->vehicle_allowance);
        $this->assertEquals(20.00, (float) $earning->amount);
        $this->assertSame('company', $earning->vehicle_ownership);
    }

    public function test_12_driver_owned_vehicle_receives_configured_compensation_plus_vehicle_allowance(): void
    {
        $manager = $this->manager();
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Allowance Driver',
            'email' => $this->uniqueEmail('driver-alw'),
            'phone' => $this->uniquePhone(),
            'password' => Hash::make('password123'),
            'status' => 'available',
        ]);

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'ALW-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'ownership_type' => 'driver_owned',
            'hub_transport_driver_id' => $driver->id,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);
        $driver->update(['logistics_truck_id' => $truck->id]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-ALW-'.rand(100, 999),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'received',
            'created_by' => $manager->id,
        ]);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-'.rand(100000, 999999),
            'center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'weight' => 5.0,
            'recipient_name' => 'Recipient',
            'recipient_phone' => $this->uniquePhone(),
            'recipient_address' => 'Dest Address',
        ]);

        \App\Support\EscrowEarnings::creditInterHubDriverOnTransferReceipt($transfer, $delivery);

        $earning = \App\Models\HubTransferEarning::where('hub_transfer_id', $transfer->id)
            ->where('hub_transport_driver_id', $driver->id)
            ->first();

        $this->assertNotNull($earning);
        $this->assertEquals(20.00, (float) $earning->base_amount);
        $this->assertEquals(15.00, (float) $earning->vehicle_allowance);
        $this->assertEquals(35.00, (float) $earning->amount);
        $this->assertSame('driver_owned', $earning->vehicle_ownership);
    }

    public function test_13_accident_report_does_not_deduct_driver_earnings(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'ACC-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Accident Driver',
            'email' => 'driver-acc'.(++$this->seq).'@test.local',
            'phone' => '09170000033',
            'password' => Hash::make('password123'),
            'logistics_truck_id' => $truck->id,
            'status' => 'available',
        ]);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-'.rand(100000, 999999),
            'center_id' => $this->center($company)->id,
            'status' => 'delivered',
            'weight' => 5.0,
            'recipient_name' => 'Recipient',
            'recipient_phone' => '09170000035',
            'recipient_address' => 'Dest Address',
        ]);

        // Driver has prior earnings
        $earning = \App\Models\HubTransferEarning::create([
            'hub_transfer_id' => null,
            'delivery_id' => $delivery->id,
            'hub_transport_driver_id' => $driver->id,
            'recipient_role' => 'driver',
            'base_amount' => 20.00,
            'vehicle_allowance' => 15.00,
            'vehicle_ownership' => 'driver_owned',
            'amount' => 35.00,
            'status' => 'credited',
        ]);

        // Severe accident is reported
        $this->actingAs($manager)->post(route('fleet.incidents.store'), [
            'logistics_truck_id' => $truck->id,
            'hub_transport_driver_id' => $driver->id,
            'incident_type' => 'accident',
            'severity' => 'severe',
            'incident_date' => now()->format('Y-m-d H:i:s'),
            'location' => 'SLEX Km 35',
            'description' => 'Rear-ended during heavy downpour.',
            'damage_details' => 'Rear axle damaged, radiator leak.',
            'vehicle_action_taken' => 'marked_damaged',
        ])->assertRedirect()->assertSessionHas('success');

        // Truck status updated to damaged
        $truck = $truck->fresh();
        $this->assertSame('damaged', $truck->status);

        // Driver earnings remain completely intact without deductions
        $this->assertSame(1, \App\Models\HubTransferEarning::where('hub_transport_driver_id', $driver->id)->count());
        $this->assertEquals(35.00, (float) $earning->fresh()->amount);
    }

    public function test_14_incident_does_not_auto_retire_and_requires_admin_review_to_retire(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'ACC-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Report Driver',
            'email' => 'driver-report'.(++$this->seq).'@test.local',
            'phone' => '09170000088',
            'password' => Hash::make('password123'),
            'logistics_truck_id' => $truck->id,
            'status' => 'available',
        ]);

        // When driver reports a total_loss incident via API
        $token = $driver->createToken('hub-driver-test')->plainTextToken;
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/logistics/courier/incidents', [
                'logistics_truck_id' => $truck->id,
                'incident_type' => 'accident',
                'severity' => 'total_loss',
                'description' => 'Severe rollover accident.',
                'damage_details' => 'Frame bent, engine destroyed.',
            ]);

        $response->assertSuccessful();

        // The truck MUST NOT be retired automatically; it becomes unavailable/damaged pending assessment
        $truck = $truck->fresh();
        $this->assertSame('damaged', $truck->status);
        $this->assertFalse($truck->isRetired());

        $incident = \App\Models\VehicleIncident::latest('id')->first();
        $this->assertSame('marked_damaged', $incident->vehicle_action_taken);

        // Logistics Admin reviews incident and decides to retire the truck
        $adminResp = $this->actingAs($manager)->put(route('fleet.incidents.update', $incident), [
            'status' => 'closed',
            'investigation_notes' => 'Inspected by fleet mechanic; repair cost exceeds value.',
            'vehicle_action_taken' => 'retired',
        ]);
        $adminResp->assertRedirect()->assertSessionHas('success');

        // Only now is the truck retired by authorized admin decision
        $truck = $truck->fresh();
        $this->assertSame('retired', $truck->status);
        $this->assertTrue($truck->isRetired());
    }

    public function test_15_admin_can_edit_truck_details(): void
    {
        $manager = $this->manager();
        $company = $this->company();

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'OLD-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'name' => 'Old Truck',
            'model' => 'Old Model',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);

        $newPlate = 'NEW-'.rand(1000, 9999);
        $resp = $this->actingAs($manager)->put(route('fleet.trucks.update', $truck), [
            'plate_number' => $newPlate,
            'vehicle_type' => 'van',
            'model' => 'Isuzu Elf New',
            'parcel_capacity' => 250,
            'capacity_kg' => 1800.50,
            'status' => 'available',
            'vehicle_registration' => 'CR-998877',
            'remarks' => 'Upgraded engine and body',
        ]);

        $resp->assertRedirect()->assertSessionHas('success');

        $truck = $truck->fresh();
        $this->assertSame($newPlate, $truck->plate_number);
        $this->assertSame('van', $truck->vehicle_type);
        $this->assertSame('Isuzu Elf New', $truck->model);
        $this->assertSame(250, $truck->parcel_capacity);
        $this->assertEquals(1800.50, (float) $truck->capacity_kg);
        $this->assertSame('CR-998877', $truck->vehicle_registration);
        $this->assertSame('Upgraded engine and body', $truck->remarks);
    }
}
