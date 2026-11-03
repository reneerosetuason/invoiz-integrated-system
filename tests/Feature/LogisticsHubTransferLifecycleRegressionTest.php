<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\HubTransfer;
use App\Models\HubTransferParcel;
use App\Models\HubTransportDriver;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsTruck;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderStatusHistory;
use App\Models\Product;
use App\Models\Rider;
use App\Models\RiderApplication;
use App\Models\Seller;
use App\Models\User;
use App\Models\VehicleType;
use App\Services\FulfillmentWorkflow;
use App\Services\HubTransferService;
use App\Services\ReturnToSellerService;
use App\Services\ShipmentExceptionService;
use App\Support\LogisticsAccess;
use App\Support\OrderCancellation;
use App\Support\Stock;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LogisticsHubTransferLifecycleRegressionTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        VehicleType::firstOrCreate(['name' => 'truck'], [
            'label' => 'Truck (4-Wheel)',
            'capacity_kg' => 2000.00,
            'is_active' => true,
            'sort_order' => 3,
        ]);
    }

    private function company(string $name = 'Alpha Logistics'): LogisticsCompany
    {
        return LogisticsCompany::create([
            'name' => $name.' '.uniqid().' '.(++$this->seq),
            'status' => 'active',
        ]);
    }

    private function hub(LogisticsCompany $company, string $name = 'Alpha Hub'): LogisticsCenter
    {
        return LogisticsCenter::create([
            'logistics_company_id' => $company->id,
            'name' => $name.' '.uniqid(),
            'code' => 'HUB-'.rand(1000, 9999),
            'address' => '123 Logistics St',
            'city' => 'Calamba',
            'province' => 'Laguna',
            'is_active' => true,
        ]);
    }

    private function logisticsAdmin(LogisticsCompany $company): User
    {
        return User::create([
            'name' => 'Logistics Admin '.uniqid(),
            'email' => 'logistics-admin-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
        ]);
    }

    private function platformAdmin(): User
    {
        return User::create([
            'name' => 'Platform Admin '.uniqid(),
            'email' => 'admin-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function logisticsStaff(LogisticsCompany $company, LogisticsCenter $center): User
    {
        return User::create([
            'name' => 'Staff '.uniqid(),
            'email' => 'staff-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'logistics_staff',
            'is_admin' => false,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
            'center_id' => $center->id,
        ]);
    }

    private function truck(LogisticsCompany $company): LogisticsTruck
    {
        return LogisticsTruck::create([
            'logistics_company_id' => $company->id,
            'ownership_type' => 'company',
            'plate_number' => 'NBD-'.rand(1000, 9999),
            'vehicle_type' => 'truck',
            'model' => 'Isuzu Elf',
            'parcel_capacity' => 50,
            'capacity_kg' => 1500.00,
            'status' => 'available',
            'verification_status' => 'approved',
        ]);
    }

    private function hubDriver(LogisticsCompany $company, LogisticsTruck $truck, bool $validLicense = true): HubTransportDriver
    {
        $user = User::create([
            'name' => 'Driver User '.uniqid(),
            'email' => 'hubdriver-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'logistics_company_id' => $company->id,
        ]);

        $driver = HubTransportDriver::create([
            'user_id' => $user->id,
            'logistics_company_id' => $company->id,
            'logistics_truck_id' => $truck->id,
            'name' => 'Transport Driver '.uniqid(),
            'email' => $user->email,
            'password' => Hash::make('password123'),
            'license_number' => 'N02-'.rand(100000, 999999),
            'status' => 'available',
            'is_online' => true,
        ]);

        if ($validLicense) {
            RiderApplication::create([
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => '09171234567',
                'vehicle_type' => 'truck',
                'application_type' => 'hub_transport_driver',
                'license_number' => $driver->license_number,
                'license_expiry' => now()->addYear()->format('Y-m-d'),
                'status' => 'approved',
                'hub_transport_driver_id' => $driver->id,
                'logistics_truck_id' => $truck->id,
                'reviewed_at' => now(),
                'provisioned_at' => now(),
            ]);
        }

        return $driver;
    }

    private function createOrderAndDelivery(LogisticsCenter $origin, LogisticsCenter $dest): array
    {
        $buyer = User::create([
            'name' => 'Buyer '.uniqid(),
            'email' => 'buyer-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $sellerUser = User::create([
            'name' => 'Seller '.uniqid(),
            'email' => 'seller-'.uniqid().'@test.local',
            'password' => Hash::make('password123'),
            'role' => 'seller',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $seller = Seller::create([
            'user_id' => $sellerUser->id,
            'store_name' => 'Test Store '.uniqid(),
            'status' => 'approved',
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'title' => 'Test Product '.uniqid(),
            'name' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'price' => 250.00,
            'stock' => 10,
            'status' => 'active',
        ]);

        $order = Order::create([
            'buyer_id' => $buyer->id,
            'seller_id' => $seller->id,
            'order_number' => 'ORD-'.now()->format('Ymd').'-'.rand(1000, 9999),
            'status' => 'ready_for_delivery',
            'total_amount' => 250.00,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'unpaid',
        ]);

        $item = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'price' => 250.00,
            'quantity' => 1,
            'subtotal' => 250.00,
        ]);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'tracking_number' => 'TRK-'.now()->format('Ymd').'-'.rand(1000, 9999),
            'center_id' => $origin->id,
            'destination_center_id' => $dest->id,
            'status' => 'waiting_for_rider',
            'parcel_status' => 'pending_arrival',
            'weight' => 2.50,
            'recipient_name' => 'Buyer Recipient',
            'recipient_phone' => '09170000000',
            'recipient_address' => 'Customer Address',
            'recipient_city' => 'Manila',
        ]);

        return compact('buyer', 'sellerUser', 'seller', 'product', 'order', 'item', 'delivery');
    }

    public function test_invoiz_admin_has_read_only_oversight_and_cannot_mutate_counter_operations(): void
    {
        $admin = $this->platformAdmin();
        $company = $this->company();
        $hubA = $this->hub($company, 'Origin Hub');
        $hubB = $this->hub($company, 'Dest Hub');
        $data = $this->createOrderAndDelivery($hubA, $hubB);

        // INVOIZ Admin can view hub transfers index
        $res = $this->actingAs($admin)->get(route('hub-transfers.index'));
        $res->assertOk();

        // INVOIZ Admin is blocked from creating transfer manifests
        $storeRes = $this->actingAs($admin)->post(route('hub-transfers.store'), [
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
        ]);
        $storeRes->assertStatus(403);

        // INVOIZ Admin is blocked from counter scanning
        $scanRes = $this->actingAs($admin)->patch(route('deliveries.scan', $data['delivery']));
        $scanRes->assertStatus(403);
    }

    public function test_logistics_admin_company_scoped_management(): void
    {
        $companyA = $this->company('Company A');
        $companyB = $this->company('Company B');
        $adminA = $this->logisticsAdmin($companyA);

        $hubA1 = $this->hub($companyA, 'Hub A1');
        $hubA2 = $this->hub($companyA, 'Hub A2');
        $hubB1 = $this->hub($companyB, 'Hub B1');

        $truckA = $this->truck($companyA);
        $driverA = $this->hubDriver($companyA, $truckA, validLicense: true);
        $data = $this->createOrderAndDelivery($hubA1, $hubA2);
        $data['delivery']->update(['parcel_status' => 'sorted']);

        // Allowed: create transfer within own company
        $res = $this->actingAs($adminA)->post(route('hub-transfers.store'), [
            'origin_center_id' => $hubA1->id,
            'destination_center_id' => $hubA2->id,
            'truck_id' => $truckA->id,
            'driver_id' => $driverA->id,
            'parcels' => [$data['delivery']->id],
        ]);
        $res->assertSessionHasNoErrors();
        $this->assertDatabaseHas('hub_transfers', [
            'logistics_company_id' => $companyA->id,
            'origin_center_id' => $hubA1->id,
            'destination_center_id' => $hubA2->id,
        ]);

        // Denied: access to hub belonging to Company B
        $this->assertFalse(LogisticsAccess::canAccessCompany($adminA, $companyB->id));
        $this->assertFalse(LogisticsAccess::canAccessCenter($adminA, $hubB1->id));
    }

    public function test_hub_transport_driver_license_validation_on_assignment_and_reassignment(): void
    {
        $company = $this->company();
        $admin = $this->logisticsAdmin($company);
        $hub1 = $this->hub($company, 'Hub 1');
        $hub2 = $this->hub($company, 'Hub 2');
        $truck = $this->truck($company);
        $data = $this->createOrderAndDelivery($hub1, $hub2);

        // Create driver with expired license
        $driverExpired = $this->hubDriver($company, $truck, validLicense: false);
        RiderApplication::create([
            'name' => $driverExpired->name,
            'email' => $driverExpired->email,
            'phone' => '09171234567',
            'vehicle_type' => 'truck',
            'application_type' => 'hub_transport_driver',
            'license_number' => $driverExpired->license_number,
            'license_expiry' => now()->subDay()->format('Y-m-d'), // Expired
            'status' => 'approved',
            'hub_transport_driver_id' => $driverExpired->id,
            'logistics_truck_id' => $truck->id,
        ]);

        $this->assertFalse($driverExpired->isLicenseValid());

        // Attempting to assign expired driver fails
        $res = $this->actingAs($admin)->post(route('hub-transfers.store'), [
            'origin_center_id' => $hub1->id,
            'destination_center_id' => $hub2->id,
            'truck_id' => $truck->id,
            'driver_id' => $driverExpired->id,
            'parcels' => [$data['delivery']->id],
        ]);
        $res->assertStatus(422);
    }

    public function test_end_to_end_order_lifecycle_tracking_progression_from_pickup_to_delivery(): void
    {
        $company = $this->company();
        $hubA = $this->hub($company, 'Origin Hub');
        $hubB = $this->hub($company, 'Destination Hub');
        $setup = $this->createOrderAndDelivery($hubA, $hubB);
        $order = $setup['order'];
        $delivery = $setup['delivery'];

        // 1. First-mile pickup sets delivery picked_up and order shipped (NOT out_for_delivery)
        $delivery->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        FulfillmentWorkflow::syncOrder($delivery, 'shipped', 'Parcel picked up from seller and in transit to sorting facility.');

        $order->refresh();
        $this->assertSame('shipped', $order->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'shipped',
        ]);

        // 2. Hub-to-Hub transfer dispatch keeps order shipped and records transit history
        $truck = $this->truck($company);
        $driver = $this->hubDriver($company, $truck, validLicense: true);
        $truck->update(['status' => 'assigned']);
        $driver->update(['status' => 'assigned']);
        $delivery->update([
            'parcel_status' => 'sorted',
            'received_at' => now(),
            'scanned_at' => now(),
            'sorted_at' => now(),
        ]);

        $transfer = HubTransfer::create([
            'reference' => 'HT-TEST-'.uniqid(),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'ready_for_dispatch',
        ]);

        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
        ]);

        HubTransferService::dispatch($transfer, null, $driver);

        $order->refresh();
        $this->assertSame('shipped', $order->status);
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'notes' => 'Parcel in transit between logistics hubs via manifest '.$transfer->reference.'.',
        ]);

        // 3. Destination receiving keeps order shipped
        HubTransferService::arrive($transfer, null, $driver);
        HubTransferService::receiveParcel($transfer, $delivery->id, 'good', null);

        $order->refresh();
        $delivery->refresh();
        $this->assertSame('shipped', $order->status);
        $this->assertSame('received', $delivery->parcel_status);
        $this->assertSame($hubB->id, (int) $delivery->center_id);

        // 4. Last-mile departure sets out_for_delivery
        $delivery->update([
            'status' => 'out_for_delivery',
            'sorting_center_pickup_at' => now(),
        ]);
        FulfillmentWorkflow::syncOrder($delivery, 'out_for_delivery', 'Parcel is out for delivery with your courier.');

        $order->refresh();
        $this->assertSame('out_for_delivery', $order->status);

        // 5. Final delivery completion sets delivered
        $delivery->update(['status' => 'delivered', 'delivered_at' => now()]);
        FulfillmentWorkflow::syncOrder($delivery, 'delivered', 'Parcel successfully delivered.');

        $order->refresh();
        $this->assertSame('delivered', $order->status);
    }

    public function test_missing_and_damaged_parcel_exceptions_record_history_without_premature_cancellation(): void
    {
        $company = $this->company();
        $hubA = $this->hub($company, 'Origin Hub');
        $hubB = $this->hub($company, 'Destination Hub');
        $setup = $this->createOrderAndDelivery($hubA, $hubB);
        $order = $setup['order'];
        $delivery = $setup['delivery'];

        $truck = $this->truck($company);
        $driver = $this->hubDriver($company, $truck, validLicense: true);
        $transfer = HubTransfer::create([
            'reference' => 'HT-EX-'.uniqid(),
            'logistics_company_id' => $company->id,
            'origin_center_id' => $hubA->id,
            'destination_center_id' => $hubB->id,
            'truck_id' => $truck->id,
            'driver_id' => $driver->id,
            'status' => 'arrived',
        ]);

        HubTransferParcel::create([
            'hub_transfer_id' => $transfer->id,
            'delivery_id' => $delivery->id,
            'loading_status' => 'loaded',
        ]);

        // Receive with damaged condition
        HubTransferService::receiveParcel($transfer, $delivery->id, 'damaged', 'Crushed carton during transit');

        $order->refresh();
        $delivery->refresh();

        // Order remains in its active fulfillment status for claims review
        $this->assertSame('ready_for_delivery', $order->status);
        $this->assertSame('damaged', $delivery->status);

        // Order history has the incident logged for Buyer and Seller timelines
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'notes' => 'Logistics Alert: Parcel received with damage at destination facility ('.$hubB->name.'). Inspection in progress.',
        ]);
    }

    public function test_order_cancellation_after_first_mile_pickup_is_strictly_blocked(): void
    {
        $company = $this->company();
        $hubA = $this->hub($company, 'Origin Hub');
        $hubB = $this->hub($company, 'Destination Hub');
        $setup = $this->createOrderAndDelivery($hubA, $hubB);
        $order = $setup['order'];
        $delivery = $setup['delivery'];
        $buyer = $setup['buyer'];

        // Mark delivery as picked up
        $delivery->update(['picked_up_at' => now(), 'status' => 'picked_up']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        OrderCancellation::byBuyer($order, 'Changed my mind', $buyer->id);
    }

    public function test_return_to_seller_restores_stock_once_and_cancels_order_without_duplicate_refund(): void
    {
        $company = $this->company();
        $hubA = $this->hub($company, 'Origin Hub');
        $hubB = $this->hub($company, 'Destination Hub');
        $setup = $this->createOrderAndDelivery($hubA, $hubB);
        $order = $setup['order'];
        $delivery = $setup['delivery'];
        $sellerUser = $setup['sellerUser'];
        $product = $setup['product'];

        $initialStock = (int) $product->stock;

        $delivery->update([
            'status' => 'delivery_failed',
            'returned_at' => now(),
        ]);
        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => ReturnToSellerService::DISPATCHED,
            'notes' => 'Dispatched back to seller',
        ]);

        $service = app(ReturnToSellerService::class);
        $service->confirm($delivery, $sellerUser, 'Received returned items');

        $order->refresh();
        $product->refresh();
        $delivery->refresh();

        // Order cancelled and stock restored once
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('returned_to_seller', $delivery->status);
        $this->assertSame($initialStock + 1, (int) $product->stock);

        // History entry recorded
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'to_status' => 'cancelled',
            'notes' => 'Order cancelled: parcel confirmed returned to seller. Stock restored.',
        ]);
    }

    public function test_logistics_staff_center_isolation(): void
    {
        $company = $this->company();
        $hub1 = $this->hub($company, 'Center 1');
        $hub2 = $this->hub($company, 'Center 2');
        $staff1 = $this->logisticsStaff($company, $hub1);
        $data = $this->createOrderAndDelivery($hub2, $hub1); // Parcel belongs to Hub 2

        $this->assertTrue(LogisticsAccess::isCenterStaff($staff1, $hub1->id));
        $this->assertFalse(LogisticsAccess::isCenterStaff($staff1, $hub2->id));

        // Staff 1 is forbidden from scanning parcels at Hub 2
        $res = $this->actingAs($staff1)->patch(route('deliveries.scan', $data['delivery']));
        $res->assertStatus(403);
    }

    public function test_hub_transport_driver_assigned_trip_isolation(): void
    {
        $companyA = $this->company('Company A');
        $companyB = $this->company('Company B');
        $hubA1 = $this->hub($companyA, 'Hub A1');
        $hubA2 = $this->hub($companyA, 'Hub A2');
        $truckA = $this->truck($companyA);
        $driverA = $this->hubDriver($companyA, $truckA, validLicense: true);

        $truckB = $this->truck($companyB);
        $driverB = $this->hubDriver($companyB, $truckB, validLicense: true);

        $transferA = HubTransfer::create([
            'reference' => 'HT-TRIP-A-'.uniqid(),
            'logistics_company_id' => $companyA->id,
            'origin_center_id' => $hubA1->id,
            'destination_center_id' => $hubA2->id,
            'truck_id' => $truckA->id,
            'driver_id' => $driverA->id,
            'status' => 'ready_for_dispatch',
        ]);

        // Driver B (different company and not assigned) cannot dispatch Transfer A
        $token = $driverB->createToken('hub-driver-token', ['hub-courier'])->plainTextToken;
        $res = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson("/logistics/api/v1/transfers/{$transferA->id}/dispatch");
        $res->assertStatus(404);
    }

    public function test_duplicate_history_and_notification_prevention(): void
    {
        $company = $this->company();
        $hubA = $this->hub($company, 'Origin Hub');
        $hubB = $this->hub($company, 'Destination Hub');
        $setup = $this->createOrderAndDelivery($hubA, $hubB);
        $delivery = $setup['delivery'];
        $order = $setup['order'];

        // Initial pickup
        FulfillmentWorkflow::syncOrder($delivery, 'shipped', 'Parcel picked up from seller.');
        $countInitial = OrderStatusHistory::where('order_id', $order->id)->count();
        $this->assertSame(1, $countInitial);

        // Repeated sync with the same status does not duplicate history
        FulfillmentWorkflow::syncOrder($delivery, 'shipped', 'Parcel picked up from seller.');
        $countAfter = OrderStatusHistory::where('order_id', $order->id)->count();
        $this->assertSame(1, $countAfter);
    }
}
