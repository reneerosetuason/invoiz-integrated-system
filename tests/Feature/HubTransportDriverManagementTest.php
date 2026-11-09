<?php

namespace Tests\Feature;

use App\Models\HubTransfer;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Rider;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HubTransportDriverManagementTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function platformAdmin(): User
    {
        return User::create([
            'phone' => '09171234567',
            'name' => 'Platform Admin',
            'email' => 'hub-driver-admin-'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function logisticsStaff(): User
    {
        return User::create([
            'phone' => '09171234567',
            'name' => 'Logistics Staff',
            'email' => 'hub-driver-staff-'.uniqid().'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'logistics_staff',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function company(): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => 'Hub Driver Co '.uniqid().' '.(++$this->seq),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company): LogisticsCenter
    {
        return LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => 'Hub '.uniqid().' '.(++$this->seq),
            'address' => '1 Transfer Street',
            'city' => 'Metro Manila',
            'is_active' => true,
        ]);
    }

    private function driver(LogisticsCompany $company, string $name): HubTransportDriver
    {
        return HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => $name,
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@test.local',
            'phone' => '09'.rand(100000000, 999999999),
            'license_number' => 'LIC-'.uniqid(),
            'password' => Hash::make('secret123'),
            'status' => 'available',
        ]);
    }

    private function truck(LogisticsCompany $company, ?HubTransportDriver $driver = null): LogisticsTruck
    {
        return LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TRK-'.uniqid(),
            'vehicle_type' => 'truck',
            'model' => 'Isuzu Elf',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'ownership_type' => 'company',
            'status' => $driver ? 'assigned' : 'available',
            'verification_status' => 'approved',
        ]);
    }

    private function transfer(LogisticsCompany $company, LogisticsCenter $origin, LogisticsCenter $destination, LogisticsTruck $truck, HubTransportDriver $driver): HubTransfer
    {
        return HubTransfer::create([
            'reference' => 'HT-'.uniqid(),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'in_transit',
        ]);
    }

    public function test_riders_sidebar_renders_dropdown_with_both_links(): void
    {
        $response = $this->actingAs($this->platformAdmin())->get('/logistics/dashboard');

        $response->assertOk();
        $response->assertSee('id="subnav-riders"', false);
        $response->assertSee('Delivery Riders');
        $response->assertSee('Hub Transport Drivers');
        $response->assertSee(route('riders.index'), false);
        $response->assertSee(route('hub-transport-drivers.index'), false);
        // Deliveries is a direct link; other dropdowns remain untouched.
        $response->assertDontSee('id="subnav-deliveries"', false);
        $response->assertSee(route('deliveries.index'), false);
        $response->assertSee('id="subnav-applications"', false);
    }

    public function test_riders_dropdown_is_expanded_on_either_child_page(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get('/logistics/riders')
            ->assertOk()
            ->assertSee('ridersOpen: true', false);

        $this->actingAs($admin)->get(route('hub-transport-drivers.index'))
            ->assertOk()
            ->assertSee('ridersOpen: true', false);
    }

    public function test_index_lists_hub_transport_drivers_and_not_delivery_riders(): void
    {
        $company = $this->company();
        $driverName = 'Hub Driver Unique '.uniqid();
        $riderName = 'Delivery Rider Unique '.uniqid();

        $this->driver($company, $driverName);

        Rider::create([
            'name' => $riderName,
            'email' => 'rider-'.uniqid().'@test.local',
            'phone' => '09123456789',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
        ]);

        $response = $this->actingAs($this->platformAdmin())
            ->get(route('hub-transport-drivers.index', ['search' => $driverName]));

        $response->assertOk();
        $response->assertSee($driverName);
        $response->assertDontSee($riderName);
    }

    public function test_index_filters_by_company_approval_and_search(): void
    {
        $companyA = $this->company();
        $companyB = $this->company();
        $driverA = $this->driver($companyA, 'Alpha Transport '.uniqid());
        $driverB = $this->driver($companyB, 'Beta Transport '.uniqid());

        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->get(route('hub-transport-drivers.index', ['logistics_company_id' => $companyA->id]))
            ->assertOk()
            ->assertSee($driverA->name)
            ->assertDontSee($driverB->name);

        $this->actingAs($admin)
            ->get(route('hub-transport-drivers.index', ['search' => $driverB->name]))
            ->assertOk()
            ->assertSee($driverB->name)
            ->assertDontSee($driverA->name);

        $this->actingAs($admin)
            ->get(route('hub-transport-drivers.index', ['approval' => 'unlinked']))
            ->assertOk()
            ->assertSee($driverA->name);
    }

    public function test_show_page_displays_profile_truck_and_transfer_history(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $destination = $this->center($company);
        $driver = $this->driver($company, 'Gamma Linehaul '.uniqid());
        $truck = $this->truck($company, $driver);
        $driver->update(['logistics_truck_id' => $truck->id]);
        $transfer = $this->transfer($company, $origin, $destination, $truck, $driver);

        $response = $this->actingAs($this->platformAdmin())
            ->get(route('hub-transport-drivers.show', $driver));

        $response->assertOk();
        $response->assertSee($driver->name);
        $response->assertSee($truck->plate_number);
        $response->assertSee($transfer->reference);
        $response->assertSee($company->name);
        $response->assertSee('Driver Assignment &amp; Status', false);
    }

    public function test_staff_cannot_access_listing_or_management_actions(): void
    {
        $company = $this->company();
        $driver = $this->driver($company, 'Restricted Driver '.uniqid());
        $staff = $this->logisticsStaff();

        $this->actingAs($staff)->get(route('hub-transport-drivers.index'))->assertForbidden();
        $this->actingAs($staff)->get(route('hub-transport-drivers.show', $driver))->assertForbidden();
        $this->actingAs($staff)
            ->put(route('fleet.drivers.update', $driver), ['status' => 'inactive'])
            ->assertForbidden();
    }

    public function test_administrator_can_update_driver_status_and_assignment(): void
    {
        $company = $this->company();
        $driver = $this->driver($company, 'Assignable Driver '.uniqid());
        $truck = $this->truck($company);
        $admin = $this->platformAdmin();

        $this->actingAs($admin)
            ->put(route('fleet.drivers.update', $driver), [
                'status' => 'available',
                'logistics_truck_id' => $truck->id,
                'remarks' => 'Assigned by logistics administrator.',
            ])
            ->assertRedirect();

        $driver->refresh();
        $this->assertSame('available', $driver->status);
        $this->assertSame($truck->id, (int) $driver->logistics_truck_id);
        $this->assertSame('Assigned by logistics administrator.', $driver->remarks);

        $this->actingAs($admin)
            ->put(route('fleet.drivers.update', $driver), ['status' => 'inactive'])
            ->assertRedirect();

        $driver->refresh();
        $this->assertSame('inactive', $driver->status);
        $this->assertNull($driver->logistics_truck_id);
    }
}
