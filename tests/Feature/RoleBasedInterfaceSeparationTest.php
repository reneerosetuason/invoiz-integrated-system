<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\ServiceArea;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RoleBasedInterfaceSeparationTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

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
    }

    private function logisticsAdmin(): User
    {
        $admin = User::query()
            ->where('email', User::GLOBAL_LOGISTICS_ADMIN_EMAIL)
            ->where('role', 'logistics_admin')
            ->first();

        if ($admin) {
            return $admin;
        }

        $admin = new User([
            'name' => 'Global Logistics Admin',
            'email' => User::GLOBAL_LOGISTICS_ADMIN_EMAIL,
            'password' => 'TestManager123!',
            'role' => 'logistics_admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->id = User::GLOBAL_LOGISTICS_ADMIN_ID;
        $admin->save();

        return $admin;
    }

    private function platformAdmin(): User
    {
        return User::create([
            'name' => 'Platform Administrator '.(++$this->seq),
            'email' => 'platform-admin'.(++$this->seq).'@test.local',
            'password' => Hash::make('PlatformAdmin123!'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function company(string $name = 'Role Separation Logistics'): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => $name.' '.(++$this->seq),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company, string $name = 'Separation Hub'): LogisticsCenter
    {
        return LogisticsCenter::create([
            'name' => $name.' '.(++$this->seq),
            'address' => '500 Logistics Expressway',
            'city' => 'Taguig',
            'is_active' => true,
            'logistics_company_id' => $company->id,
        ]);
    }

    private function staffUser(LogisticsCompany $company, LogisticsCenter $center): User
    {
        return User::create([
            'name' => 'Hub Operations Staff '.(++$this->seq),
            'email' => 'staff'.(++$this->seq).'@test.local',
            'password' => Hash::make('StaffPassword123!'),
            'role' => 'logistics_staff',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'center_id' => $center->id,
            'logistics_company_id' => $company->id,
            'email_verified_at' => now(),
        ]);
    }

    private function createTruck(LogisticsCompany $company, string $plate = 'ABC-1234', string $status = 'available'): LogisticsTruck
    {
        return LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => $plate.'-'.(++$this->seq),
            'name' => 'Linehaul Truck '.(++$this->seq),
            'vehicle_type' => 'truck',
            'model' => 'Isuzu Elf Forward',
            'parcel_capacity' => 150,
            'capacity_kg' => 2500.00,
            'status' => $status,
        ]);
    }

    private function createDriver(LogisticsCompany $company, ?LogisticsTruck $truck = null): HubTransportDriver
    {
        return HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck?->id,
            'name' => 'Driver '.(++$this->seq),
            'email' => 'driver'.(++$this->seq).'@test.local',
            'password' => 'DriverPass123!',
            'phone' => '09170001122',
            'license_number' => 'N01-12-'.(++$this->seq),
            'status' => 'available',
            'is_online' => true,
        ]);
    }

    public function test_logistics_admin_sidebar_has_management_finance_and_no_hub_transfers(): void
    {
        $admin = $this->logisticsAdmin();

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertOk();
        // Main Navigation
        $response->assertSee('Deliveries');
        $response->assertSee('Fleet Management');
        $response->assertSee('Riders');
        $response->assertSee('Pickup Requests');

        // Counter operations belong to Hub Staff. The Logistics Admin keeps
        // read-only monitoring and management and must not see these links.
        $response->assertDontSee('aria-label="Hub Transfers"', false);
        $response->assertDontSee('aria-label="Sorting Center"', false);
        $response->assertDontSee('aria-label="Scan Parcel"', false);

        // Management section
        $response->assertSee('Management');
        $response->assertSee('Logistics Hubs');
        $response->assertSee('Staff');
        $response->assertSee('Applications');
        $response->assertSee('Rider Applications');
        $response->assertSee('Logistics Hub Applications');
        $response->assertSee('Service Areas');

        // Finance and Reports section
        $response->assertSee('Finance and Reports');
        $response->assertSee('Transactions');
        $response->assertSee('Remittances');
        $response->assertSee('Reports');

        // Other section
        $response->assertSee('Other');
        $response->assertSee('Messages');
        $response->assertSee('Archived');
    }

    public function test_logistics_staff_sidebar_has_daily_operations_and_no_admin_sections(): void
    {
        $company = $this->company();
        $center = $this->center($company);
        $staff = $this->staffUser($company, $center);

        $response = $this->actingAs($staff)->get(route('dashboard'));

        $response->assertOk();
        // Staff Main Navigation
        $response->assertSee('Deliveries');
        $response->assertSee('Sorting Center');
        $response->assertSee('Hub Transfers');
        $response->assertSee('Pickup Requests');
        $response->assertSee('Messages');

        // Admin modules MUST NOT be in the Staff sidebar
        $response->assertDontSee('aria-label="Fleet Management"', false);
        $response->assertDontSee('aria-label="Riders"', false);
        $response->assertDontSee('aria-label="Logistics Hubs"', false);
        $response->assertDontSee('aria-label="Staff"', false);
        $response->assertDontSee('aria-label="Rider Applications"', false);
        $response->assertDontSee('aria-label="Logistics Hub Applications"', false);
        $response->assertDontSee('aria-label="Service Areas"', false);
        $response->assertDontSee('Finance and Reports');
        $response->assertDontSee('aria-label="Transactions"', false);
        $response->assertDontSee('aria-label="Remittances"', false);
        $response->assertDontSee('aria-label="Reports"', false);
        $response->assertDontSee('aria-label="Archived"', false);
    }

    public function test_fleet_management_accessible_to_admin_with_manual_forms_removed_and_metrics_displayed(): void
    {
        $admin = $this->logisticsAdmin();
        $company = $this->company();
        $truck = $this->createTruck($company);
        $driver = $this->createDriver($company, $truck);

        $response = $this->actingAs($admin)->get(route('fleet.index'));

        $response->assertOk();
        // Overview metrics
        $response->assertSee('Total Trucks');
        $response->assertSee('Available');
        $response->assertSee('Assigned');
        $response->assertSee('Under Maintenance');
        $response->assertSee('Approved Drivers');

        // Verification of manual forms removed
        $response->assertDontSee('Register Truck');
        $response->assertDontSee('Register Driver Account');

        // Verification of displayed truck and driver records
        $response->assertSee($truck->plate_number);
        $response->assertSee($driver->name);
        $response->assertSee($driver->email);
        $response->assertSee('Automated Fleet Onboarding');
    }

    public function test_fleet_management_assignment_and_maintenance_updates(): void
    {
        $admin = $this->logisticsAdmin();
        $company = $this->company();
        $truck = $this->createTruck($company);
        $driver = $this->createDriver($company, null); // Unassigned

        // Admin updates truck status to maintenance with remarks
        $this->actingAs($admin)->put(route('fleet.trucks.update', $truck), [
            'status' => 'maintenance',
            'remarks' => 'Oil leak inspected, awaiting replacement gasket.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('maintenance', $truck->fresh()->status);
        $this->assertSame('Oil leak inspected, awaiting replacement gasket.', $truck->fresh()->remarks);

        // Attempting to assign a truck under maintenance to a driver must fail
        $this->actingAs($admin)->put(route('fleet.drivers.update', $driver), [
            'status' => 'available',
            'logistics_truck_id' => $truck->id,
        ])->assertStatus(422);

        // Put truck back to available
        $this->actingAs($admin)->put(route('fleet.trucks.update', $truck), [
            'status' => 'available',
            'remarks' => 'Repairs completed and tested.',
        ])->assertSessionHasNoErrors();

        // Assigning an available four-wheel truck succeeds
        $this->actingAs($admin)->put(route('fleet.drivers.update', $driver), [
            'status' => 'available',
            'logistics_truck_id' => $truck->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($truck->id, $driver->fresh()->logistics_truck_id);
    }

    public function test_logistics_staff_is_denied_access_to_fleet_management_and_admin_pages(): void
    {
        $company = $this->company();
        $center = $this->center($company);
        $staff = $this->staffUser($company, $center);
        $truck = $this->createTruck($company);

        // Fleet management administration is blocked with HTTP 403
        $this->actingAs($staff)->get(route('fleet.index'))->assertForbidden();
        $this->actingAs($staff)->put(route('fleet.trucks.update', $truck), ['status' => 'maintenance'])->assertForbidden();

        // Management pages blocked with HTTP 403
        $this->actingAs($staff)->get(route('centers.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('staff.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('rider-applications.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('center-applications.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('service-areas.index'))->assertForbidden();

        // Finance and network reports blocked with HTTP 403
        $this->actingAs($staff)->get(route('transactions.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('remittances.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('deliveries.archived'))->assertForbidden();
    }

    public function test_logistics_staff_can_access_daily_hub_operations(): void
    {
        $company = $this->company();
        $center = $this->center($company);
        $staff = $this->staffUser($company, $center);

        // Staff operational modules
        $this->actingAs($staff)->get(route('sorting-center.index'))->assertOk();
        $this->actingAs($staff)->get(route('hub-transfers.index'))->assertOk();
        $this->actingAs($staff)->get(route('hub-transfers.create'))->assertOk();
        $this->actingAs($staff)->get(route('pickup-requests.index'))->assertOk();
        $this->actingAs($staff)->get(route('messages.index'))->assertOk();
    }

    public function test_admin_can_monitor_hub_transfers_without_operational_controls(): void
    {
        $admin = $this->logisticsAdmin();
        $company = $this->company();
        $origin = $this->center($company, 'Origin Hub');
        $destination = $this->center($company, 'Dest Hub');
        $truck = $this->createTruck($company);
        $driver = $this->createDriver($company, $truck);

        $transfer = HubTransfer::create([
            'reference' => 'HT-MONITOR-01',
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'loading',
            'created_by' => $admin->id,
        ]);

        // Hub Transfers index: Admin sees the monitoring list but NOT the operational "Create Transfer" button
        $indexResponse = $this->actingAs($admin)->get(route('hub-transfers.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('Create Transfer');

        // Hub Transfer detail: Admin sees Administrative Oversight mode but NOT the staff operational forms
        $showResponse = $this->actingAs($admin)->get(route('hub-transfers.show', $transfer));
        $showResponse->assertOk();
        $showResponse->assertSee('Administrative Oversight Mode');
        $showResponse->assertDontSee('Confirm Dispatch');
        $showResponse->assertDontSee('Confirm Arrival');
        $showResponse->assertDontSee('Finalize Receiving');
    }

    public function test_platform_administrator_access_and_navigation_preserved(): void
    {
        $platformAdmin = $this->platformAdmin();

        // Platform admin retains admin navigation and access to administration
        $this->assertTrue($platformAdmin->isAdmin());
        $this->actingAs($platformAdmin)->get(route('fleet.index'))->assertOk();
        $this->actingAs($platformAdmin)->get(route('centers.index'))->assertOk();
        $this->actingAs($platformAdmin)->get(route('transactions.index'))->assertOk();
        $this->actingAs($platformAdmin)->get(route('reports.index'))->assertOk();
    }
}
