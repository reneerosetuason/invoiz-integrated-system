<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryProof;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Rider;
use App\Models\Seller;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryOtpTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function scenario(bool $withBuyer = true, string $status = 'out_for_delivery'): array
    {
        $this->seq++;

        $seller = Seller::factory()->create();

        $buyer = User::create([
            'name' => 'OTP Buyer',
            'email' => "otp-buyer-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
        ]);

        $riderUser = User::create([
            'name' => 'OTP Rider',
            'email' => "otp-rider-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $rider = Rider::create([
            'user_id' => $riderUser->id,
            'name' => 'OTP Rider',
            'email' => $riderUser->email,
            'phone' => '09170000000',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);

        $order = Order::create([
            'order_number' => 'OTP-ORDER-'.$this->seq,
            'seller_id' => $seller->id,
            'buyer_id' => $withBuyer ? $buyer->id : null,
            'status' => 'out_for_delivery',
            'payment_status' => 'pending',
            'total' => 120,
            'total_amount' => 120,
            'sub_total' => 120,
            'shipping_fee' => 50,
        ]);

        $product = Product::create([
            'seller_id' => $seller->id,
            'category_id' => null,
            'name' => "OTP Product {$this->seq}",
            'slug' => "otp-product-{$this->seq}",
            'status' => 'active',
            'price' => 60,
            'stock' => 10,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 60,
            'total_price' => 120,
        ]);

        $delivery = Delivery::create([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'rider_id' => $rider->id,
            'status' => $status,
            'tracking_number' => 'TRK-OTP-'.$this->seq,
            'sender_name' => 'OTP Sender',
            'recipient_name' => 'OTP Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'payment_method' => 'prepaid',
            'delivery_fee' => Order::SHIPPING_FEE_PER_SELLER,
        ]);

        return [$buyer, $riderUser, $rider, $seller, $order, $delivery];
    }

    private function otpUrl(Order $order, Delivery $delivery): string
    {
        return "/api/orders/{$order->id}/deliveries/{$delivery->id}/otp";
    }

    private function completeUrl(Delivery $delivery): string
    {
        return "/api/logistics/rider/deliveries/{$delivery->id}/complete";
    }

    public function test_buyer_can_obtain_otp_for_own_eligible_delivery(): void
    {
        [$buyer, , , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($buyer);

        $response = $this->getJson($this->otpUrl($order, $delivery))
            ->assertOk()
            ->assertJsonPath('delivery_id', $delivery->id)
            ->assertJsonPath('tracking_number', $delivery->tracking_number);

        $otp = $response->json('otp');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $otp);
        $this->assertGreaterThan(0, $response->json('attempts_remaining'));

        $fresh = $delivery->fresh();
        $this->assertSame($otp, $fresh->delivery_otp);
        $this->assertNotSame($otp, DB::table('deliveries')->where('id', $delivery->id)->value('delivery_otp'));
    }

    public function test_rider_payload_flags_otp_required_without_exposing_the_code(): void
    {
        [$buyer, $riderUser, , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($buyer);
        $this->getJson($this->otpUrl($order, $delivery))->assertOk();

        Sanctum::actingAs($riderUser);
        $response = $this->getJson("/api/logistics/rider/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertJsonPath('delivery.otp_required', true);

        $this->assertArrayNotHasKey('delivery_otp', $response->json('delivery'));
        $this->assertArrayNotHasKey('delivery_otp_expires_at', $response->json('delivery'));
    }

    public function test_another_buyer_cannot_obtain_the_otp(): void
    {
        [, , , , $order, $delivery] = $this->scenario();
        $otherBuyer = User::create([
            'name' => 'Other Buyer',
            'email' => 'other-otp-buyer@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
        ]);

        Sanctum::actingAs($otherBuyer);

        $this->getJson($this->otpUrl($order, $delivery))->assertNotFound();
        $this->assertNull($delivery->fresh()->delivery_otp);
    }

    public function test_rider_cannot_access_the_buyer_otp_endpoint(): void
    {
        [, $riderUser, , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($riderUser);

        $this->getJson($this->otpUrl($order, $delivery))->assertForbidden();
        $this->assertNull($delivery->fresh()->delivery_otp);
    }

    public function test_otp_is_not_available_before_out_for_delivery(): void
    {
        [$buyer, , , , $order, $delivery] = $this->scenario(status: 'picked_up');

        Sanctum::actingAs($buyer);

        $this->getJson($this->otpUrl($order, $delivery))
            ->assertStatus(409)
            ->assertJsonPath('message', 'The delivery OTP is available once the parcel is out for delivery.');

        $this->assertNull($delivery->fresh()->delivery_otp);
    }

    public function test_missing_and_incorrect_otp_do_not_complete_the_delivery(): void
    {
        [$buyer, $riderUser, , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($buyer);
        $otp = $this->getJson($this->otpUrl($order, $delivery))->assertOk()->json('otp');

        Sanctum::actingAs($riderUser);

        $this->postJson($this->completeUrl($delivery))
            ->assertStatus(422)
            ->assertJsonPath('errors.otp.0', 'Enter the 6-digit delivery OTP provided by the buyer.');

        $this->assertSame('out_for_delivery', $delivery->fresh()->status);

        $wrong = $otp === '000000' ? '111111' : '000000';

        $this->postJson($this->completeUrl($delivery), ['otp' => $wrong])
            ->assertStatus(422)
            ->assertJsonPath('errors.otp.0', 'Incorrect delivery OTP. 4 attempts remaining.');

        $this->assertSame('out_for_delivery', $delivery->fresh()->status);
        $this->assertSame(1, $delivery->fresh()->delivery_otp_attempts);
        $this->assertSame(0, DeliveryProof::where('delivery_id', $delivery->id)->count());

        $response = $this->postJson($this->completeUrl($delivery), ['otp' => $otp])
            ->assertOk()
            ->assertJsonPath('message', 'Delivery completed successfully.');

        $this->assertArrayNotHasKey('delivery_otp', $response->json('delivery'));
        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertNotNull($delivery->fresh()->delivery_otp_consumed_at);
        $this->assertNull(DeliveryProof::where('delivery_id', $delivery->id)->value('otp'));
    }

    public function test_buyer_order_payload_does_not_expose_otp_metadata(): void
    {
        [$buyer, , , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($buyer);
        $this->getJson($this->otpUrl($order, $delivery))->assertOk();

        $response = $this->getJson('/api/orders')->assertOk();
        $deliveryPayload = $response->json('orders.0.delivery');

        $this->assertIsArray($deliveryPayload);

        foreach ([
            'delivery_otp',
            'delivery_otp_expires_at',
            'delivery_otp_attempts',
            'delivery_otp_consumed_at',
        ] as $key) {
            $this->assertArrayNotHasKey($key, $deliveryPayload);
        }
    }

    public function test_expired_otp_is_rejected_and_buyer_can_request_a_new_code(): void
    {
        [$buyer, $riderUser, , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($buyer);
        $oldOtp = $this->getJson($this->otpUrl($order, $delivery))->assertOk()->json('otp');

        $this->travel(31)->minutes();

        Sanctum::actingAs($riderUser);
        $this->postJson($this->completeUrl($delivery), ['otp' => $oldOtp])
            ->assertStatus(422)
            ->assertJsonPath('errors.otp.0', 'The delivery OTP has expired. Ask the buyer to request a new code.');

        $this->assertSame('out_for_delivery', $delivery->fresh()->status);

        Sanctum::actingAs($buyer);
        $newOtp = $this->getJson($this->otpUrl($order, $delivery))->assertOk()->json('otp');
        $this->assertNotSame($oldOtp, $newOtp);

        Sanctum::actingAs($riderUser);
        $this->postJson($this->completeUrl($delivery), ['otp' => $newOtp])->assertOk();

        $this->assertSame('delivered', $delivery->fresh()->status);

        $this->travelBack();
    }

    public function test_exhausted_attempts_require_a_new_buyer_code(): void
    {
        [$buyer, $riderUser, , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($buyer);
        $otp = $this->getJson($this->otpUrl($order, $delivery))->assertOk()->json('otp');
        $wrong = $otp === '000000' ? '111111' : '000000';

        Sanctum::actingAs($riderUser);

        foreach (range(1, 5) as $attempt) {
            $response = $this->postJson($this->completeUrl($delivery), ['otp' => $wrong]);
            $response->assertStatus(422);

            if ($attempt < 5) {
                $remaining = 5 - $attempt;
                $response->assertJsonPath(
                    'errors.otp.0',
                    'Incorrect delivery OTP. '.$remaining.' '.($remaining === 1 ? 'attempt' : 'attempts').' remaining.',
                );
            }
        }

        $this->assertSame(5, $delivery->fresh()->delivery_otp_attempts);
        $this->assertSame('out_for_delivery', $delivery->fresh()->status);

        $this->postJson($this->completeUrl($delivery), ['otp' => $otp])
            ->assertStatus(422)
            ->assertJsonPath('errors.otp.0', 'Too many incorrect delivery OTP attempts. Ask the buyer to request a new code.');

        Sanctum::actingAs($buyer);
        $newOtp = $this->getJson($this->otpUrl($order, $delivery))->assertOk()->json('otp');
        $this->assertNotSame($otp, $newOtp);

        Sanctum::actingAs($riderUser);
        $this->postJson($this->completeUrl($delivery), ['otp' => $newOtp])->assertOk();
        $this->assertSame('delivered', $delivery->fresh()->status);
    }

    public function test_non_buyer_delivery_keeps_legacy_completion_without_otp(): void
    {
        [, $riderUser, , , , $delivery] = $this->scenario(withBuyer: false);

        Sanctum::actingAs($riderUser);

        $this->postJson($this->completeUrl($delivery))
            ->assertOk()
            ->assertJsonPath('message', 'Delivery completed successfully.');

        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->delivery_otp);
    }
}
