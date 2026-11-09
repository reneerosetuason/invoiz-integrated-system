<?php

namespace Tests\Feature;

use App\Models\EscrowRemittance;
use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Rider;
use App\Models\User;
use App\Support\EscrowEarnings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Verifies Sanctum token separation between Delivery Riders and Hub Couriers,
 * and asserts idempotency of inter-hub transfer trip earnings.
 */
class RiderAndCourierSanctumSeparationTest extends TestCase
{
    use DatabaseTransactions;

    private function createRider(): array
    {
        $email = 'rider-' . bin2hex(random_bytes(4)) . '@test.local';

        $user = User::create([
            'name' => 'Test Delivery Rider',
            'email' => $email,
            'password' => Hash::make('password123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'phone' => '09' . rand(100000000, 999999999),
            'email_verified_at' => now(),
        ]);

        $rider = Rider::create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $email,
            'phone' => $user->phone,
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'RDR-' . rand(1000, 9999),
            'status' => 'available',
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear(),
        ]);

        return [$user, $rider];
    }

    private function createCourier(): array
    {
        $company = LogisticsCompany::create([
            'name' => 'Sanctum Separation Freight Co ' . bin2hex(random_bytes(3)),
            'status' => 'active',
        ]);

        $email = 'courier-' . bin2hex(random_bytes(4)) . '@test.local';

        $driver = HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Test Hub Driver',
            'email' => $email,
            'password' => 'password123',
            'phone' => '09' . rand(100000000, 999999999),
            'license_number' => 'N01-' . rand(10, 99) . '-' . rand(100000, 999999),
            'status' => 'available',
            'is_online' => false,
            'license_expiry' => now()->addYear(),
        ]);

        $truck = LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'HTD-' . rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 200,
            'capacity_kg' => 2000,
            'ownership_type' => 'company',
            'hub_transport_driver_id' => $driver->id,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);

        $driver->update(['logistics_truck_id' => $truck->id]);

        return [$company, $driver, $truck];
    }

    public function test_delivery_rider_can_access_rider_endpoints_but_is_forbidden_from_courier_endpoints(): void
    {
        [$riderUser, $rider] = $this->createRider();

        Sanctum::actingAs($riderUser, ['rider']);

        // Legitimate rider endpoints return HTTP 200
        $this->getJson('/api/logistics/rider/profile')->assertOk();
        $this->getJson('/api/logistics/rider/dashboard')->assertOk();
        $this->getJson('/api/logistics/rider/deliveries')->assertOk();
        $this->getJson('/api/logistics/rider/earnings')->assertOk();

        // Cross-role attempts to access courier endpoints return HTTP 403 Forbidden
        $this->getJson('/api/logistics/courier/profile')->assertStatus(403);
        $this->getJson('/api/logistics/courier/dashboard')->assertStatus(403);
        $this->getJson('/api/logistics/courier/transfers')->assertStatus(403);
        $this->getJson('/api/logistics/courier/earnings')->assertStatus(403);
    }

    public function test_hub_courier_can_access_courier_endpoints_but_is_forbidden_from_rider_endpoints(): void
    {
        [$company, $driver, $truck] = $this->createCourier();

        Sanctum::actingAs($driver, ['hub-courier']);

        // Legitimate hub courier endpoints return HTTP 200
        $this->getJson('/api/logistics/courier/profile')->assertOk();
        $this->getJson('/api/logistics/courier/dashboard')->assertOk();
        $this->getJson('/api/logistics/courier/transfers')->assertOk();
        $this->getJson('/api/logistics/courier/earnings')->assertOk();

        // Cross-role attempts to access rider endpoints return HTTP 403 Forbidden
        $this->getJson('/api/logistics/rider/profile')->assertStatus(403);
        $this->getJson('/api/logistics/rider/dashboard')->assertStatus(403);
        $this->getJson('/api/logistics/rider/deliveries')->assertStatus(403);
        $this->getJson('/api/logistics/rider/earnings')->assertStatus(403);
    }

    public function test_hub_transfer_trip_earnings_are_idempotent_and_prevent_duplicate_rows(): void
    {
        [$company, $driver, $truck] = $this->createCourier();

        $origin = LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => 'Origin Hub ' . bin2hex(random_bytes(3)),
            'city' => 'Manila',
            'province' => 'Metro Manila',
            'is_active' => true,
        ]);
        $destination = LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => 'Destination Hub ' . bin2hex(random_bytes(3)),
            'city' => 'Laguna',
            'province' => 'Laguna',
            'is_active' => true,
        ]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-IDEMP-' . strtoupper(bin2hex(random_bytes(4))),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $destination->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'received',
            'received_at' => now()->toDateTimeString(),
        ]);

        // Remittance record so the earning passes HubTransferEarning::acceptedRemittances() scope
        EscrowRemittance::create([
            'logistics_company_id' => $company->id,
            'hub_transfer_id' => $transfer->id,
            'hub_transport_driver_id' => $driver->id,
            'recipient_role' => EscrowRemittance::ROLE_INTER_HUB_DRIVER,
            'amount' => 20.00,
            'status' => EscrowRemittance::STATUS_ACCEPTED,
        ]);

        // First credit call
        $firstEarning = EscrowEarnings::creditInterHubDriverForCompletedTrip($transfer);
        $this->assertNotNull($firstEarning, 'First credit call must create an earning');

        // Immediate retry / repeated execution on same transfer
        $secondEarning = EscrowEarnings::creditInterHubDriverForCompletedTrip($transfer);
        $this->assertNotNull($secondEarning);
        $this->assertSame($firstEarning->id, $secondEarning->id, 'Second credit call must return the existing earning');

        // Confirm DB has exactly one row for this trip & role
        $queryCount = HubTransferEarning::where('hub_transfer_id', $transfer->id)
            ->where('recipient_role', HubTransferEarning::ROLE_INTER_HUB_DRIVER)
            ->count();
        $this->assertSame(1, $queryCount, 'Database must contain exactly 1 earning record for this trip and role');

        // Verify API output contains exactly 1 earning and 0 duplicate groups
        Sanctum::actingAs($driver, ['hub-courier']);
        $response = $this->getJson('/api/logistics/courier/earnings')->assertOk();
        $response->assertJsonCount(1, 'earnings');

        $driverEarnings = HubTransferEarning::where('hub_transport_driver_id', $driver->id)->get();
        $duplicates = $driverEarnings
            ->groupBy(fn ($row) => $row->hub_transfer_id . '|' . $row->recipient_role)
            ->filter(fn ($group) => $group->count() > 1);

        $this->assertCount(0, $duplicates, 'Driver must have zero duplicate trip earning groups');
    }
}
