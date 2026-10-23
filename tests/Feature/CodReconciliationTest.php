<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Remittance;
use App\Models\Seller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CodReconciliationTest extends TestCase
{
    use DatabaseTransactions;

    private function user(string $role): User
    {
        $email = "cod-{$role}-" . uniqid() . '@test.local';

        return User::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => $role,
            'is_admin' => $role === 'admin',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function remittance(float $collected, float $sellerAmount): Remittance
    {
        $seller = Seller::factory()->create();
        $buyer = $this->user('buyer');

        $order = Order::create([
            'order_number' => 'COD-' . uniqid(),
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
            'status' => 'delivered',
            'payment_status' => 'pending',
            'total' => 170,
            'total_amount' => 170,
            'sub_total' => 120,
            'shipping_fee' => 50,
        ]);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'status' => 'delivered',
            'delivered_at' => now(),
            'sender_name' => 'COD Sender',
            'recipient_name' => 'COD Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 170,
            'cod_amount_received' => $collected,
            'cod_received_at' => now(),
            'delivery_fee' => Order::SHIPPING_FEE_PER_SELLER,
        ]);

        Transaction::create([
            'delivery_id' => $delivery->id,
            'tracking_number' => $delivery->tracking_number,
            'amount' => Order::SHIPPING_FEE_PER_SELLER,
            'rider_fee' => Order::SHIPPING_FEE_PER_SELLER,
            'logistics_fee' => Order::LOGISTICS_FEE_PER_DELIVERY,
            'admin_commission' => 0,
            'status' => 'completed',
        ]);

        return Remittance::create([
            'order_id' => $order->id,
            'delivery_id' => $delivery->id,
            'seller_id' => $seller->id,
            'amount' => $sellerAmount,
            'status' => 'pending',
        ]);
    }

    public function test_logistics_oversight_shows_balanced_and_variance_cash(): void
    {
        $admin = $this->user('admin');
        $balanced = $this->remittance(170, 120);
        $variance = $this->remittance(170, 130);

        $response = $this->actingAs($admin)->get('/logistics/remittances');

        $response->assertOk()
            ->assertSee('COD to Collect')
            ->assertSee('Collected')
            ->assertSee('Variance')
            ->assertViewHas('reconciliation', function ($rows) use ($balanced, $variance) {
                return ($rows[$balanced->id]['state'] ?? null) === 'balanced'
                    && abs((float) ($rows[$balanced->id]['variance'] ?? 1)) < 0.001
                    && ($rows[$variance->id]['state'] ?? null) === 'variance'
                    && abs((float) ($rows[$variance->id]['variance'] ?? 0) + 10.0) < 0.001;
            });
    }
}
