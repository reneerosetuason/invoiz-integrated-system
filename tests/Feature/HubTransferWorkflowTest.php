<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HubTransferWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private int $sequence = 0;

    private function company(string $name): LogisticsCompany
    {
        return LogisticsCompany::create(['name' => $name, 'status' => 'active']);
    }

    private function center(LogisticsCompany $company, string $name): LogisticsCenter
    {
        return LogisticsCenter::create(['name' => $name, 'address' => '1 Test Road', 'city' => 'Test City', 'is_active' => true, 'logistics_company_id' => $company->id]);
    }

    private function admin(): User
    {
        $user = new User([
            'name' => 'Global Transfer Admin', 'email' => 'logistics_admin@test.com',
            'password' => Hash::make('secret123'), 'role' => 'logistics_admin', 'status' => 'active',
            'account_status' => 'active', 'is_admin' => false, 'email_verified_at' => now(),
        ]);
        $user->id = 18;
        $user->save();
        return $user;
    }

    private function delivery(LogisticsCenter $origin, LogisticsCenter $destination): Delivery
    {
        $this->sequence++;
        return Delivery::create([
            'tracking_number' => 'TRK-TEST-'.str_pad((string) $this->sequence, 5, '0', STR_PAD_LEFT),
            'sender_name' => 'Sender', 'recipient_name' => 'Recipient',
            'sender_address' => 'Sender Road', 'recipient_address' => 'Destination Road',
            'center_id' => $origin->id, 'destination_center_id' => $destination->id,
            'status' => 'waiting_for_rider', 'parcel_status' => 'sorted', 'weight' => 2,
        ]);
    }

    private function fleet(LogisticsCompany $company): array
    {
        $truck = LogisticsTruck::create(['logistics_company_id' => $company->id, 'plate_number' => 'TEST-'.$company->id, 'vehicle_type' => 'truck', 'parcel_capacity' => 10, 'capacity_kg' => 500, 'status' => 'available']);
        $driver = HubTransportDriver::create(['logistics_company_id' => $company->id, 'logistics_truck_id' => $truck->id, 'name' => 'Transport Driver', 'email' => 'driver'.$company->id.'@test.local', 'password' => Hash::make('secret123'), 'status' => 'available', 'is_online' => true]);
        return [$truck, $driver];
    }

    public function test_transfer_moves_multiple_parcels_only_after_individual_receiving(): void
    {
        $company = $this->company('Transfer Company');
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        $admin = $this->admin();
        [$truck, $driver] = $this->fleet($company);
        $first = $this->delivery($origin, $destination);
        $second = $this->delivery($origin, $destination);

        $response = $this->actingAs($admin)->post(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id, 'destination_center_id' => $destination->id,
            'truck_id' => $truck->id, 'driver_id' => $driver->id, 'parcels' => [$first->id, $second->id],
        ]);
        $transfer = HubTransfer::firstOrFail();
        $response->assertRedirect(route('hub-transfers.show', $transfer));
        $this->assertCount(2, $transfer->parcels);

        foreach ([$first, $second] as $parcel) {
            $this->post(route('hub-transfers.load', $transfer), ['tracking_number' => $parcel->tracking_number])->assertSessionHasNoErrors();
        }
        $this->post(route('hub-transfers.dispatch', $transfer))->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.arrive', $transfer))->assertSessionHasNoErrors();

        $this->post(route('hub-transfers.receive', $transfer), ['tracking_number' => $first->tracking_number, 'condition' => 'good'])->assertSessionHasNoErrors();
        $this->assertSame($origin->id, $first->fresh()->center_id);
        $this->assertSame($destination->id, $second->fresh()->center_id);

        $this->post(route('hub-transfers.receive', $transfer), ['tracking_number' => $second->tracking_number, 'condition' => 'good'])->assertSessionHasNoErrors();
        $this->post(route('hub-transfers.complete', $transfer))->assertSessionHasNoErrors();

        $this->assertSame($destination->id, $first->fresh()->center_id);
        $this->assertSame($destination->id, $second->fresh()->center_id);
        $this->assertSame('received', $transfer->fresh()->status);
        $this->assertSame('available', $truck->fresh()->status);
    }

    public function test_cross_company_destination_and_duplicate_active_parcel_are_rejected(): void
    {
        $companyA = $this->company('Company A');
        $companyB = $this->company('Company B');
        $origin = $this->center($companyA, 'A Origin');
        $destination = $this->center($companyB, 'B Destination');
        $localDestination = $this->center($companyA, 'A Destination');
        $admin = $this->admin();
        [$truck, $driver] = $this->fleet($companyA);
        $parcel = $this->delivery($origin, $localDestination);

        $payload = ['logistics_company_id' => $companyA->id, 'origin_center_id' => $origin->id, 'destination_center_id' => $destination->id, 'truck_id' => $truck->id, 'driver_id' => $driver->id, 'parcels' => [$parcel->id]];
        $this->actingAs($admin)->from(route('hub-transfers.create'))->post(route('hub-transfers.store'), $payload)->assertSessionHasErrors('destination_center_id');

        $payload['destination_center_id'] = $localDestination->id;
        $this->post(route('hub-transfers.store'), $payload)->assertSessionHasNoErrors();
        $transfer = HubTransfer::latest('id')->firstOrFail();
        $staff = User::create([
            'name' => 'Company B Staff', 'email' => 'company-b-staff@test.local', 'password' => Hash::make('secret123'),
            'role' => 'logistics_staff', 'status' => 'active', 'account_status' => 'active',
            'center_id' => $destination->id, 'logistics_company_id' => $companyB->id,
        ]);
        $this->actingAs($staff)->get(route('hub-transfers.show', $transfer))->assertNotFound();
        $this->actingAs($admin)->get(route('hub-transfers.index'))->assertOk()->assertViewHas('transfers', function ($page) {
            return $page->total() === 1;
        });
        $secondTruck = LogisticsTruck::create(['logistics_company_id' => $companyA->id, 'plate_number' => 'TEST-SECOND', 'vehicle_type' => 'truck', 'parcel_capacity' => 10, 'capacity_kg' => 500, 'status' => 'available']);
        $secondDriver = HubTransportDriver::create(['logistics_company_id' => $companyA->id, 'logistics_truck_id' => $secondTruck->id, 'name' => 'Second Driver', 'email' => 'second@test.local', 'password' => Hash::make('secret123'), 'status' => 'available', 'is_online' => true]);
        $payload['truck_id'] = $secondTruck->id;
        $payload['driver_id'] = $secondDriver->id;
        $this->post(route('hub-transfers.store'), $payload)->assertStatus(422);
    }

    public function test_courier_can_only_view_own_transfer_and_arrival_does_not_receive_parcels(): void
    {
        $company = $this->company('Courier Company');
        $origin = $this->center($company, 'Courier Origin');
        $destination = $this->center($company, 'Courier Destination');
        [$truck, $driver] = $this->fleet($company);
        $delivery = $this->delivery($origin, $destination);
        $transfer = HubTransfer::create([
            'reference' => 'HT-COURIER-1', 'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id, 'destination_center_id' => $destination->id,
            'truck_id' => $truck->id, 'driver_id' => $driver->id, 'status' => 'in_transit',
        ]);
        $manifest = HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id, 'delivery_id' => $delivery->id,
            'loading_status' => 'loaded', 'receiving_status' => 'pending',
        ]);

        Sanctum::actingAs($driver, ['hub-courier']);
        $this->getJson('/api/logistics/courier/transfers/'.$transfer->id)->assertOk()->assertJsonPath('transfer.reference', 'HT-COURIER-1');
        $this->postJson('/api/logistics/courier/transfers/'.$transfer->id.'/destination-arrival')->assertOk()->assertJsonPath('transfer.status', 'arrived');
        $this->assertSame('pending', $manifest->fresh()->receiving_status);
        $this->assertSame($origin->id, $delivery->fresh()->center_id);
    }

    public function test_courier_cannot_read_another_drivers_transfer(): void
    {
        $company = $this->company('Courier Isolation Company');
        $origin = $this->center($company, 'Origin');
        $destination = $this->center($company, 'Destination');
        [$truck, $assignedDriver] = $this->fleet($company);
        $outsider = HubTransportDriver::create(['logistics_company_id' => $company->id, 'name' => 'Other Driver', 'email' => 'other@test.local', 'password' => Hash::make('secret123'), 'status' => 'available']);
        $transfer = HubTransfer::create(['reference' => 'HT-PRIVATE-1', 'logistics_company_id' => $company->id, 'origin_center_id' => $origin->id, 'destination_center_id' => $destination->id, 'truck_id' => $truck->id, 'driver_id' => $assignedDriver->id, 'status' => 'assigned']);
        Sanctum::actingAs($outsider, ['hub-courier']);
        $this->getJson('/api/logistics/courier/transfers/'.$transfer->id)->assertNotFound();
    }

}
