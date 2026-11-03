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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryOtpTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

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
            'approval_status' => 'approved',
            'email_verified_at' => now(),
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

    private function photo(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            'delivery-proof.png',
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII='),
        );
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

    public function test_rider_payload_no_longer_flags_or_exposes_a_delivery_otp(): void
    {
        [$buyer, $riderUser, , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($buyer);
        $this->getJson($this->otpUrl($order, $delivery))->assertOk();

        Sanctum::actingAs($riderUser, ['rider']);
        $response = $this->getJson("/api/logistics/rider/deliveries/{$delivery->id}")
            ->assertOk();

        $this->assertArrayNotHasKey('otp_required', $response->json('delivery'));
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
            'approval_status' => 'approved',
            'email_verified_at' => now(),
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

    public function test_expired_code_can_be_regenerated_by_the_buyer(): void
    {
        [$buyer, , , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($buyer);
        $oldOtp = $this->getJson($this->otpUrl($order, $delivery))->assertOk()->json('otp');

        $this->travel(31)->minutes();

        $newOtp = $this->getJson($this->otpUrl($order, $delivery))->assertOk()->json('otp');
        $this->assertNotSame($oldOtp, $newOtp);

        $this->travelBack();
    }

    public function test_rider_completion_needs_only_a_photo_not_an_otp(): void
    {
        [$buyer, $riderUser, , , $order, $delivery] = $this->scenario();

        Sanctum::actingAs($buyer);
        $this->getJson($this->otpUrl($order, $delivery))->assertOk();
        $this->assertNotNull($delivery->fresh()->delivery_otp);

        Sanctum::actingAs($riderUser, ['rider']);

        $this->postJson($this->completeUrl($delivery), ['photo' => $this->photo()])
            ->assertOk()
            ->assertJsonPath('message', 'Delivery completed successfully.');

        $fresh = $delivery->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertNull($fresh->delivery_otp_consumed_at);

        $this->assertDatabaseHas('delivery_proofs', [
            'delivery_id' => $delivery->id,
            'type' => 'photo',
        ]);
        $this->assertNull(DeliveryProof::where('delivery_id', $delivery->id)->value('otp'));
    }
}
