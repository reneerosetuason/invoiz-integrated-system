<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\DeliveryFailure;
use App\Models\DeliveryProof;
use App\Models\DeliveryStatusLog;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Remittance;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\Seller;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ShipmentExceptionAndRtsTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function rider(): array
    {
        $this->seq++;
        $email = "exception-rider-{$this->seq}@test.local";

        $user = User::create([
            'name' => 'Exception Rider',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $rider = Rider::create([
            'user_id' => $user->id,
            'name' => 'Exception Rider',
            'email' => $email,
            'phone' => '09170000001',
            'vehicle_type' => 'motorcycle',
            'status' => 'available',
            'is_online' => true,
            'is_verified' => true,
            'approved_at' => now(),
            'vehicle_verification' => 'verified',
            'license_expiry' => now()->addYear()->format('Y-m-d'),
        ]);

        return [$user->fresh(), $rider];
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Exception Admin',
            'email' => 'exception-admin-'.bin2hex(random_bytes(4)).'@test.local',
            'password' => Hash::make('secret123'),
            'role' => 'admin',
            'is_admin' => true,
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function sellerAccount(): array
    {
        $this->seq++;
        $user = User::create([
            'name' => 'Return Seller',
            'email' => "exception-seller-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => 'seller',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $seller = Seller::create([
            'user_id' => $user->id,
            'store_name' => 'Return Store',
            'status' => 'approved',
        ]);

        return [$user->fresh(), $seller];
    }

    private function orderWithStock(Seller $seller, int $stock = 10): array
    {
        $this->seq++;

        $buyer = User::create([
            'name' => 'Return Buyer',
            'email' => "exception-buyer-{$this->seq}@test.local",
            'password' => Hash::make('secret123'),
            'role' => 'buyer',
            'status' => 'active',
            'account_status' => 'active',
        ]);

        $order = Order::create([
            'seller_id' => $seller->id,
            'buyer_id' => $buyer->id,
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
            'name' => "Return Product {$this->seq}",
            'slug' => "return-product-{$this->seq}",
            'status' => 'active',
            'price' => 60,
            'stock' => $stock,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'seller_id' => $seller->user_id,
            'product_name' => $product->name,
            'quantity' => 2,
            'unit_price' => 60,
            'total_price' => 120,
        ]);

        return [$order, $product];
    }

    private function delivery(array $extra = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'status' => 'out_for_delivery',
            'tracking_number' => 'TRK-EXC-'.$this->seq,
            'sender_name' => 'Exception Sender',
            'recipient_name' => 'Exception Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'payment_method' => 'prepaid',
        ], $extra));
    }

    private function financialCount(Delivery $delivery): array
    {
        return [
            'transactions' => Transaction::where('delivery_id', $delivery->id)->count(),
            'remittances' => Remittance::where('delivery_id', $delivery->id)->count(),
            'earnings' => RiderEarning::where('delivery_id', $delivery->id)->count(),
        ];
    }

    public function test_rider_damaged_report_requires_photo_and_does_not_move_stock_or_money(): void
    {
        Storage::fake('public');
        [$user, $rider] = $this->rider();
        [$order, $product] = $this->orderWithStock(Seller::factory()->create());
        $delivery = $this->delivery([
            'order_id' => $order->id,
            'seller_id' => $order->seller_id,
            'rider_id' => $rider->id,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/exception", [
            'type' => 'damaged',
            'reason' => 'Contents broken',
        ])->assertStatus(422)->assertJsonValidationErrors('photo');

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/exception", [
            'type' => 'damaged',
            'reason' => 'Contents broken',
            'notes' => 'Box crushed',
            'photo' => UploadedFile::fake()->createWithContent(
                'damage.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z2nYAAAAASUVORK5CYII='),
            ),
        ])->assertOk()->assertJsonPath('delivery.status', 'damaged');

        $fresh = $delivery->fresh();
        $this->assertSame('damaged', $fresh->status);
        $this->assertNotNull($fresh->failed_at);
        $this->assertNull($fresh->returned_at);
        $this->assertSame(1, DeliveryFailure::where('delivery_id', $delivery->id)->count());
        $this->assertSame(1, DeliveryProof::where('delivery_id', $delivery->id)->where('type', 'damage_evidence')->count());
        $this->assertSame('damaged', DeliveryStatusLog::where('delivery_id', $delivery->id)->latest('id')->value('status'));
        $this->assertSame(10, (int) $product->fresh()->stock);
        $this->assertSame(['transactions' => 0, 'remittances' => 0, 'earnings' => 0], $this->financialCount($delivery));

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/exception", [
            'type' => 'lost',
            'reason' => 'Suspected theft',
        ])->assertStatus(422)->assertJsonValidationErrors('type');

        $this->assertSame(1, DeliveryFailure::where('delivery_id', $delivery->id)->count());
        $this->assertSame('damaged', $delivery->fresh()->status);
    }

    public function test_lost_parcel_cannot_be_returned_but_damaged_parcel_can(): void
    {
        Storage::fake('public');
        [$user, $rider] = $this->rider();
        $admin = $this->admin();

        $lost = $this->delivery([
            'rider_id' => $rider->id,
            'status' => 'lost',
            'returned_at' => now(),
        ]);
        $damaged = $this->delivery([
            'rider_id' => $rider->id,
            'status' => 'damaged',
            'returned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('deliveries.return-to-seller', $lost))
            ->assertRedirect()
            ->assertSessionHasErrors('return');

        $this->actingAs($admin)
            ->post(route('deliveries.return-to-seller', $damaged), ['reference' => 'HANDOFF-1'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            DeliveryStatusLog::where('delivery_id', $damaged->id)->where('status', 'return_dispatched_to_seller')->exists()
        );

        $this->actingAs($admin)
            ->post(route('deliveries.return-to-seller', $damaged), ['reference' => 'HANDOFF-1'])
            ->assertRedirect();

        $this->assertSame(
            1,
            DeliveryStatusLog::where('delivery_id', $damaged->id)->where('status', 'return_dispatched_to_seller')->count()
        );
    }

    public function test_rider_can_return_damaged_parcel_to_center(): void
    {
        [$user, $rider] = $this->rider();
        $delivery = $this->delivery([
            'rider_id' => $rider->id,
            'status' => 'damaged',
            'failure_reason' => 'Damaged during transit',
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/return-to-center")
            ->assertOk();

        $this->assertNotNull($delivery->fresh()->returned_at);
        $this->assertTrue(
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'returned_to_center')->exists()
        );
    }

    public function test_seller_confirmation_restores_stock_once_and_creates_no_money(): void
    {
        [$sellerUser, $seller] = $this->sellerAccount();
        [$order, $product] = $this->orderWithStock($seller);
        $delivery = $this->delivery([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'status' => 'damaged',
            'returned_at' => now(),
            'failure_reason' => 'Damaged packaging',
        ]);

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status' => 'return_dispatched_to_seller',
            'notes' => 'Handed to courier',
        ]);

        $this->actingAs($sellerUser)
            ->withSession(['active_role' => 'seller'])
            ->get(route('seller.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Returned Parcel')
            ->assertSee($delivery->tracking_number);

        $this->actingAs($sellerUser)
            ->withSession(['active_role' => 'seller'])
            ->post(route('seller.returns.confirm', $delivery), ['note' => 'Box received'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('returned_to_seller', $delivery->fresh()->status);
        $this->assertSame(12, (int) $product->fresh()->stock);
        $this->assertSame(
            1,
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'return_received_by_seller')->count()
        );
        $this->assertSame(['transactions' => 0, 'remittances' => 0, 'earnings' => 0], $this->financialCount($delivery));

        $this->actingAs($sellerUser)
            ->withSession(['active_role' => 'seller'])
            ->post(route('seller.returns.confirm', $delivery))
            ->assertRedirect();

        $this->assertSame(12, (int) $product->fresh()->stock);
        $this->assertSame(
            1,
            DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'return_received_by_seller')->count()
        );
    }

    public function test_other_seller_cannot_confirm_return_and_confirmation_requires_dispatch(): void
    {
        [$sellerUser, $seller] = $this->sellerAccount();
        [$otherUser] = $this->sellerAccount();
        [$order] = $this->orderWithStock($seller);

        $delivery = $this->delivery([
            'order_id' => $order->id,
            'seller_id' => $seller->id,
            'status' => 'damaged',
            'returned_at' => now(),
        ]);

        $this->actingAs($otherUser)
            ->withSession(['active_role' => 'seller'])
            ->post(route('seller.returns.confirm', $delivery))
            ->assertForbidden();

        $this->actingAs($sellerUser)
            ->withSession(['active_role' => 'seller'])
            ->post(route('seller.returns.confirm', $delivery))
            ->assertRedirect()
            ->assertSessionHasErrors('return');

        $this->assertSame('damaged', $delivery->fresh()->status);
    }
}
