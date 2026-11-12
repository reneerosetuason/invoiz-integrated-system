<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\HubTransfer;
use App\Models\HubTransferEarning;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Seller;
use App\Models\User;
use App\Services\HubTransferService;
use App\Support\EscrowEarnings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HubTransportDriverTripEarningsTest extends TestCase
{
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
            'name' => 'Global Logistics Admin',
            'phone' => fake()->unique()->numerify('09#########'),
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

    private function company(): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => 'Test Company '.uniqid(),
            'status' => 'active',
        ]);
    }

    private function center(LogisticsCompany $company): LogisticsCenter
    {
        return LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => 'Hub-'.rand(1000, 9999),
            'code' => 'HUB-'.rand(100, 999),
            'address' => 'Center Address',
            'city' => 'Metro Manila',
            'status' => 'active',
        ]);
    }

    private function driver(LogisticsCompany $company): HubTransportDriver
    {
        return HubTransportDriver::create([
            'logistics_company_id' => $company->id,
            'name' => 'Driver-'.rand(1000, 9999),
            'email' => 'driver-'.rand(10000, 99999).'@test.com',
            'phone' => '09'.rand(100000000, 999999999),
            'password' => Hash::make('password123'),
            'status' => 'available',
        ]);
    }

    private function truck(LogisticsCompany $company, string $ownership = 'company', ?HubTransportDriver $driver = null): LogisticsTruck
    {
        return LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'plate_number' => 'TRK-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'parcel_capacity' => 100,
            'capacity_kg' => 1000,
            'ownership_type' => $ownership,
            'hub_transport_driver_id' => $driver?->id,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);
    }

    private function createTransfer(LogisticsCompany $company, LogisticsCenter $origin, LogisticsCenter $dest, LogisticsTruck $truck, HubTransportDriver $driver, int $parcelCount = 1): HubTransfer
    {
        $transfer = HubTransfer::create([
            'reference' => 'HT-TRIP-'.rand(10000, 99999),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'arrived',
            'created_by' => $this->manager()->id,
        ]);

        for ($i = 0; $i < $parcelCount; $i++) {
            $delivery = Delivery::create([
                'tracking_number' => 'TRK-'.uniqid().'-'.$i,
                'center_id' => $origin->id,
                'destination_center_id' => $dest->id,
                'status' => 'waiting_for_rider',
                'parcel_status' => 'received',
                'weight' => 2.0,
                'recipient_name' => 'Recipient '.$i,
                'recipient_phone' => '09123456789',
                'recipient_address' => 'Recipient Address',
            ]);

            HubTransferParcel::create([
                'hub_transfer_id' => $transfer->id,
                'delivery_id' => $delivery->id,
                'loading_status' => 'loaded',
                'receiving_status' => 'received',
            ]);
        }

        return $transfer;
    }

    /**
     * 1. A completed transfer with one parcel creates one driver earnings record.
     */
    public function test_completed_transfer_with_one_parcel_creates_one_driver_earnings_record(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, 'company');

        $transfer = $this->createTransfer($company, $origin, $dest, $truck, $driver, 1);

        HubTransferService::complete($transfer, $this->manager());

        $earnings = HubTransferEarning::where('hub_transfer_id', $transfer->id)->get();
        $this->assertCount(1, $earnings);
        $this->assertNull($earnings->first()->delivery_id);
        $this->assertEquals((float) config('earnings.inter_hub_driver_fee', 20.00), (float) $earnings->first()->base_amount);
        $this->assertEquals(0.00, (float) $earnings->first()->vehicle_allowance);
        $this->assertEquals((float) config('earnings.inter_hub_driver_fee', 20.00), (float) $earnings->first()->amount);
        $this->assertSame('company', $earnings->first()->vehicle_ownership);
    }

    /**
     * 2. A completed transfer with 40 parcels still creates only one driver earnings record (does not multiply by parcel count).
     */
    public function test_completed_transfer_with_40_parcels_still_creates_one_record(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, 'company');

        $transfer = $this->createTransfer($company, $origin, $dest, $truck, $driver, 40);

        HubTransferService::complete($transfer, $this->manager());

        $earnings = HubTransferEarning::where('hub_transfer_id', $transfer->id)->get();
        $this->assertCount(1, $earnings);
        $this->assertEquals((float) config('earnings.inter_hub_driver_fee', 20.00), (float) $earnings->first()->base_amount);
        $this->assertEquals((float) config('earnings.inter_hub_driver_fee', 20.00), (float) $earnings->first()->amount);
    }

    /**
     * 3. Driver-owned trucks receive the configured vehicle allowance only once.
     */
    public function test_driver_owned_truck_receives_configured_vehicle_allowance_only_once(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, 'driver_owned', $driver);
        $driver->update(['logistics_truck_id' => $truck->id]);

        $transfer = $this->createTransfer($company, $origin, $dest, $truck, $driver, 15);

        HubTransferService::complete($transfer, $this->manager());

        $earnings = HubTransferEarning::where('hub_transfer_id', $transfer->id)->get();
        $this->assertCount(1, $earnings);

        $expectedBase = (float) config('earnings.inter_hub_driver_fee', 20.00);
        $expectedAllowance = (float) config('earnings.inter_hub_driver_vehicle_allowance', 15.00);
        $expectedTotal = $expectedBase + $expectedAllowance;

        $this->assertEquals($expectedBase, (float) $earnings->first()->base_amount);
        $this->assertEquals($expectedAllowance, (float) $earnings->first()->vehicle_allowance);
        $this->assertEquals($expectedTotal, (float) $earnings->first()->amount);
        $this->assertSame('driver_owned', $earnings->first()->vehicle_ownership);
    }

    /**
     * 4. Company-owned trucks do not receive the driver-owned allowance.
     */
    public function test_company_owned_truck_does_not_receive_vehicle_allowance(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, 'company');

        $transfer = $this->createTransfer($company, $origin, $dest, $truck, $driver, 5);

        HubTransferService::complete($transfer, $this->manager());

        $earning = HubTransferEarning::where('hub_transfer_id', $transfer->id)->firstOrFail();
        $this->assertEquals(0.00, (float) $earning->vehicle_allowance);
        $this->assertSame('company', $earning->vehicle_ownership);
    }

    /**
     * 5. Repeated completion calls do not create duplicate earnings.
     */
    public function test_repeated_completion_calls_do_not_create_duplicate_earnings(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, 'company');

        $transfer = $this->createTransfer($company, $origin, $dest, $truck, $driver, 3);

        HubTransferService::complete($transfer, $this->manager());
        $this->assertCount(1, HubTransferEarning::where('hub_transfer_id', $transfer->id)->get());

        // Repeated call on already completed transfer
        HubTransferService::complete($transfer, $this->manager());
        $this->assertCount(1, HubTransferEarning::where('hub_transfer_id', $transfer->id)->get());
    }

    /**
     * 6. Concurrent completion attempts cannot double-credit.
     */
    public function test_concurrent_completion_attempts_cannot_double_credit(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, 'company');

        $transfer = $this->createTransfer($company, $origin, $dest, $truck, $driver, 2);

        // Simulate two concurrent threads executing creditInterHubDriverForCompletedTrip()
        EscrowEarnings::creditInterHubDriverForCompletedTrip($transfer);
        EscrowEarnings::creditInterHubDriverForCompletedTrip($transfer);

        $this->assertCount(1, HubTransferEarning::where('hub_transfer_id', $transfer->id)->get());
    }

    /**
     * 7. Incomplete transfers do not receive trip compensation.
     */
    public function test_incomplete_transfers_do_not_receive_trip_compensation(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, 'company');

        $transfer = HubTransfer::create([
            'reference' => 'HT-INC-'.rand(1000, 9999),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'receiving',
            'created_by' => $this->manager()->id,
        ]);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-INC-'.rand(100000, 999999),
            'center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'weight' => 2.0,
            'recipient_name' => 'Recipient Inc',
            'recipient_phone' => '09123456789',
            'recipient_address' => 'Recipient Address',
        ]);

        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
            'receiving_status' => 'pending', // Still pending!
        ]);

        // Attempting complete() before receiving all parcels throws 409
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Every parcel must be received or recorded as missing before completion.');

        try {
            HubTransferService::complete($transfer, $this->manager());
        } finally {
            $this->assertCount(0, HubTransferEarning::where('hub_transfer_id', $transfer->id)->get());
        }
    }

    /**
     * 8. Existing seller remittance calculations remain unchanged.
     */
    public function test_existing_seller_remittance_calculations_remain_unchanged(): void
    {
        $company = $this->company();
        $origin = $this->center($company);
        $dest = $this->center($company);
        $driver = $this->driver($company);
        $truck = $this->truck($company, 'company');

        $sellerUser = User::create([
            'name' => 'Test Seller',
            'phone' => fake()->unique()->numerify('09#########'),
            'email' => 'seller.'.rand(1000, 9999).'@test.com',
            'password' => Hash::make('password123'),
            'role' => 'seller',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $seller = Seller::forceCreate([
            // Keep the fixture compatible with legacy seller FKs targeting users.
            'id' => $sellerUser->id,
            'user_id' => $sellerUser->id,
            'store_name' => 'Trip Test Store',
            'line_of_business' => 'Retail',
            'status' => 'active',
        ]);

        $category = \App\Models\Category::create(['name' => 'Trip Test Category '.\Illuminate\Support\Str::ulid()]);
        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => $category->id,
            'name' => 'Trip Test Item',
            'slug' => 'trip-test-item-'.rand(1000, 9999),
            'price' => 500.00,
            'stock' => 10,
            'status' => 'active',
        ]);

        $buyer = $this->manager();
        $address = \App\Models\Address::create([
            'buyer_id' => $buyer->id,
            'recipient_name' => 'Trip Test Buyer',
            'phone' => '09123456789',
            'address_line' => 'Test Address',
            'barangay' => 'Test Barangay',
            'city' => 'Test City',
            'province' => 'Test Province',
        ]);
        $order = Order::create([
            'order_number' => 'ORD-TRIP-'.rand(10000, 99999),
            'user_id' => $this->manager()->id,
            'buyer_id' => $buyer->id,
            'address_id' => $address->id,
            'seller_id' => $seller->id,
            'status' => 'pending',
            'payment_status' => 'paid',
            'total_amount' => 550.00,
            'shipping_fee' => 50.00,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'price' => 500.00,
            'quantity' => 1,
            'total' => 500.00,
        ]);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-ORD-'.rand(100000, 999999),
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'weight' => 1.5,
            'recipient_name' => 'Buyer',
            'recipient_phone' => '09123456789',
            'recipient_address' => 'Buyer Addr',
        ]);

        $calcBefore = EscrowEarnings::calculateForSeller($order, $seller->id, $delivery);

        $transfer = $this->createTransfer($company, $origin, $dest, $truck, $driver, 1);
        HubTransferService::complete($transfer, $this->manager());

        $calcAfter = EscrowEarnings::calculateForSeller($order, $seller->id, $delivery);

        $this->assertEquals($calcBefore['seller_earning'], $calcAfter['seller_earning']);
        $this->assertEquals($calcBefore['admin_commission'], $calcAfter['admin_commission']);
        $this->assertEquals($calcBefore['buyer_shipping_base'], $calcAfter['buyer_shipping_base']);
        $this->assertTrue($calcAfter['conserved']);
    }

    /**
     * 9. Historical per-parcel earnings records remain accessible.
     */
    public function test_historical_per_parcel_earnings_records_remain_accessible(): void
    {
        $company = $this->company();
        $driver = $this->driver($company);

        $delivery = Delivery::create([
            'tracking_number' => 'TRK-HIST-'.rand(100000, 999999),
            'status' => 'waiting_for_rider',
            'parcel_status' => 'received',
            'recipient_name' => 'Hist Recipient',
            'recipient_phone' => '09123456789',
            'recipient_address' => 'Hist Address',
        ]);

        // Create a historical per-delivery record
        $historical = HubTransferEarning::create([
            'logistics_company_id' => $company->id,
            'hub_transfer_id' => null,
            'delivery_id' => $delivery->id,
            'hub_transport_driver_id' => $driver->id,
            'recipient_role' => HubTransferEarning::ROLE_INTER_HUB_DRIVER,
            'base_amount' => 20.00,
            'vehicle_allowance' => 0.00,
            'vehicle_ownership' => 'company',
            'amount' => 20.00,
            'earned_on' => now()->toDateString(),
            'description' => 'Historical per-parcel earning',
        ]);

        $found = EscrowEarnings::interHubEarningFor($delivery);
        $this->assertNotNull($found);
        $this->assertEquals($historical->id, $found->id);
        $this->assertEquals(20.00, (float) $found->amount);
    }
}
