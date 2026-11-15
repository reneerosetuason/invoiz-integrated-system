<?php

namespace Tests\Support;

use App\Models\Delivery;
use App\Models\DeliveryStatusLog;
use App\Models\LogisticsCenter;
use App\Models\LogisticsCompany;
use App\Models\LogisticsSetting;
use App\Models\Order;
use App\Models\Rider;
use App\Models\User;
use App\Services\ReturnToSellerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FailedDeliveryDatabase;
use Tests\TestCase;

trait FailedDeliveryScenario
{
    private User $staff;
    private User $riderUser;
    private Rider $rider;
    private LogisticsCenter $hub;
    private Delivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        FailedDeliveryDatabase::create();
        $company = LogisticsCompany::create(['name' => 'INVOIZ Logistics', 'status' => 'active']);
        $this->hub = LogisticsCenter::create(['name' => 'Destination Hub', 'is_active' => true,
            'logistics_company_id' => $company->id]);
        $this->staff = $this->identity('staff', 'staff@example.test');
        $this->staff->update(['center_id' => $this->hub->id, 'logistics_company_id' => $company->id]);
        $this->riderUser = $this->identity('rider', 'rider@example.test');
        $this->rider = $this->eligibleRider($this->riderUser);
        $order = Order::create(['status' => 'out_for_delivery', 'payment_status' => 'paid',
            'buyer_id' => 900, 'seller_id' => 901, 'total_amount' => 300, 'total' => 300]);
        $this->delivery = Delivery::create(['tracking_number' => 'TRK-REDELIVERY-TEST',
            'order_id' => $order->id, 'seller_id' => 901, 'rider_id' => $this->rider->id,
            'center_id' => $this->hub->id, 'destination_center_id' => $this->hub->id,
            'status' => 'out_for_delivery', 'parcel_status' => 'dispatched',
            'sender_name' => 'Synthetic Seller', 'recipient_name' => 'Synthetic Buyer',
            'recipient_address' => 'Synthetic Destination', 'weight' => 1,
            'delivery_fee' => 50, 'amount_to_collect' => 300, 'payment_method' => 'cash_on_delivery',
            'received_at' => now()->subHour(), 'scanned_at' => now()->subHour(), 'sorted_at' => now()->subHour(),
            'picked_up_at' => now()->subHours(2), 'sorting_center_handoff_at' => now()->subHour(),
            'sorting_center_handoff_rider_id' => $this->rider->id, 'dispatched_at' => now(),
            'sorting_center_pickup_at' => now(), 'sorting_center_pickup_rider_id' => $this->rider->id]);
    }

    private function identity(string $role, string $email): User
    {
        return User::create(['name' => 'Synthetic '.$role, 'email' => $email,
            'password' => 'isolated-fixture-only', 'role' => $role, 'status' => 'active',
            'account_status' => 'active', 'email_verified_at' => now()]);
    }

    private function eligibleRider(User $user): Rider
    {
        return Rider::create(['user_id' => $user->id, 'name' => $user->name,
            'email' => $user->email, 'center_id' => $this->hub->id, 'is_online' => true,
            'status' => 'available', 'vehicle_type' => 'motorcycle', 'vehicle_verification' => 'verified',
            'vehicle_capacity_kg' => 50, 'is_verified' => true, 'approved_at' => now()]);
    }

    private function riderPost(string $action, array $data = [])
    {
        Sanctum::actingAs($this->riderUser, ['rider']);
        return $this->postJson('/api/logistics/rider/deliveries/'.$this->delivery->id.'/'.$action, $data);
    }

}
