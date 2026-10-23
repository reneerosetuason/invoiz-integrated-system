<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Delivery;
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
use App\Services\DeliveryOtpService;
use App\Support\Cod;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Focused coverage for the rider completion boundary: delivery state,
 * status log, transaction, remittance, and rider earning must commit as
 * one unit, and a required settlement failure must roll all of them back.
 */
class RiderDeliveryCompletionTest extends TestCase
{
    use DatabaseTransactions;

    private int $seq = 0;

    private function riderUser(): array
    {
        $this->seq++;
        $email = "completion-rider-{$this->seq}@test.local";

        $user = User::create([
            'name' => 'Completion Rider',
            'email' => $email,
            'password' => Hash::make('secret123'),
            'role' => 'rider',
            'status' => 'active',
            'account_status' => 'active',
            'email_verified_at' => now(),
        ]);

        $rider = Rider::create([
            'user_id' => $user->id,
            'name' => 'Completion Rider',
            'email' => $email,
            'phone' => '09170000000',
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

    private function order(): array
    {
        $this->seq++;

        $seller = Seller::factory()->create();

        $buyer = User::create([
            'name' => 'Completion Buyer',
            'email' => "completion-buyer-{$this->seq}@test.local",
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
            'name' => "Completion Product {$this->seq}",
            'slug' => "completion-product-{$this->seq}",
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

        return [$order, $seller];
    }

    private function delivery(Rider $rider, ?Order $order, array $extra = []): Delivery
    {
        $this->seq++;

        return Delivery::create(array_merge([
            'order_id' => $order?->id,
            'seller_id' => $order?->seller_id,
            'rider_id' => $rider->id,
            'status' => 'out_for_delivery',
            'tracking_number' => 'TRK-COMPLETE-'.$this->seq,
            'sender_name' => 'Completion Sender',
            'recipient_name' => 'Completion Recipient',
            'sender_address' => 'Sender Address',
            'recipient_address' => 'Recipient Address',
            'payment_method' => 'prepaid',
            'delivery_fee' => Order::SHIPPING_FEE_PER_SELLER,
        ], $extra));
    }

    private function otpFor(Delivery $delivery): string
    {
        return app(DeliveryOtpService::class)->issueFor($delivery);
    }

    private function financialCounts(Delivery $delivery): array
    {
        return [
            'transactions' => Transaction::where('delivery_id', $delivery->id)->count(),
            'remittances' => Remittance::where('delivery_id', $delivery->id)->count(),
            'earnings' => RiderEarning::where('delivery_id', $delivery->id)->count(),
        ];
    }

    public function test_completion_commits_delivery_transaction_remittance_and_earning_together(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $delivery = $this->delivery($rider, $order);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'proof_type' => 'signature',
            'signature_name' => 'Completion Recipient',
            'otp' => $this->otpFor($delivery),
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Delivery completed successfully.')
            ->assertJsonPath('earned', 50);

        $fresh = $delivery->fresh();

        $this->assertSame('delivered', $fresh->status);
        $this->assertNotNull($fresh->delivered_at);
        $this->assertSame(1, DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'delivered')->count());
        $this->assertSame(1, DeliveryProof::where('delivery_id', $delivery->id)->count());

        $this->assertDatabaseHas('transactions', [
            'delivery_id' => $delivery->id,
            'rider_id' => $rider->id,
            'amount' => 50,
            'rider_fee' => 50,
            'logistics_fee' => Order::LOGISTICS_FEE_PER_DELIVERY,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('remittances', [
            'delivery_id' => $delivery->id,
            'order_id' => $order->id,
            'rider_id' => $rider->id,
            'seller_id' => $order->seller_id,
            'amount' => 120,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('rider_earnings', [
            'delivery_id' => $delivery->id,
            'rider_id' => $rider->id,
            'type' => 'delivery',
            'amount' => 50,
        ]);
    }

    public function test_earning_uses_the_delivery_fee_and_falls_back_to_the_flat_fee(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $delivery = $this->delivery($rider, $order, ['delivery_fee' => 65]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'otp' => $this->otpFor($delivery),
        ])
            ->assertOk()
            ->assertJsonPath('earned', 65);

        $this->assertDatabaseHas('rider_earnings', [
            'delivery_id' => $delivery->id,
            'amount' => 65,
        ]);
        $this->assertDatabaseHas('transactions', [
            'delivery_id' => $delivery->id,
            'amount' => 65,
            'rider_fee' => 65,
        ]);
    }

    public function test_required_settlement_failure_rolls_back_delivery_and_financial_writes(): void
    {
        [$user, $rider] = $this->riderUser();
        $delivery = $this->delivery($rider, null, ['order_id' => 999999999]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'proof_type' => 'signature',
        ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Delivery completion failed. No changes were saved.');

        $this->assertSame('out_for_delivery', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->delivered_at);
        $this->assertSame(0, DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'delivered')->count());
        $this->assertSame(0, DeliveryProof::where('delivery_id', $delivery->id)->count());
        $this->assertSame([
            'transactions' => 0,
            'remittances' => 0,
            'earnings' => 0,
        ], $this->financialCounts($delivery));
    }

    public function test_repeating_completion_does_not_duplicate_financial_records(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $delivery = $this->delivery($rider, $order);
        $url = "/api/logistics/rider/deliveries/{$delivery->id}/complete";

        Sanctum::actingAs($user);

        $this->postJson($url, ['otp' => $this->otpFor($delivery)])->assertOk();
        $this->postJson($url)->assertStatus(409);

        $this->assertSame([
            'transactions' => 1,
            'remittances' => 1,
            'earnings' => 1,
        ], $this->financialCounts($delivery));

        $this->assertSame(1, DeliveryStatusLog::where('delivery_id', $delivery->id)->where('status', 'delivered')->count());
    }

    public function test_another_rider_cannot_complete_the_delivery(): void
    {
        [$user, $rider] = $this->riderUser();
        [$intruder] = $this->riderUser();
        [$order] = $this->order();
        $delivery = $this->delivery($rider, $order);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete")
            ->assertForbidden();

        $this->assertSame('out_for_delivery', $delivery->fresh()->status);
        $this->assertSame([
            'transactions' => 0,
            'remittances' => 0,
            'earnings' => 0,
        ], $this->financialCounts($delivery));
    }

    public function test_completion_rejects_an_invalid_status(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $delivery = $this->delivery($rider, $order, ['status' => 'picked_up']);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete")
            ->assertStatus(409);

        $this->assertSame('picked_up', $delivery->fresh()->status);
        $this->assertSame([
            'transactions' => 0,
            'remittances' => 0,
            'earnings' => 0,
        ], $this->financialCounts($delivery));
    }

    public function test_cod_requires_a_cash_amount_before_any_writes(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $delivery = $this->delivery($rider, $order, [
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 120,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'otp' => $this->otpFor($delivery),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount_received');

        $this->assertSame('out_for_delivery', $delivery->fresh()->status);
        $this->assertSame(0, DeliveryProof::where('delivery_id', $delivery->id)->count());
        $this->assertSame([
            'transactions' => 0,
            'remittances' => 0,
            'earnings' => 0,
        ], $this->financialCounts($delivery));
    }

    public function test_cod_completion_with_the_exact_amount_preserves_proof_behavior(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $delivery = $this->delivery($rider, $order, [
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 120,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'amount_received' => 120,
            'signature_name' => 'Completion Recipient',
            'otp' => $this->otpFor($delivery),
        ])->assertOk();

        $this->assertSame('delivered', $delivery->fresh()->status);
        $this->assertDatabaseHas('delivery_proofs', [
            'delivery_id' => $delivery->id,
            'rider_id' => $rider->id,
            'type' => 'signature',
            'signature_name' => 'Completion Recipient',
        ]);
        $this->assertSame([
            'transactions' => 1,
            'remittances' => 1,
            'earnings' => 1,
        ], $this->financialCounts($delivery));
    }

    public function test_cod_completion_records_the_cash_and_audit_trail(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $order->update(['total' => 170, 'total_amount' => 170, 'shipping_fee' => 50]);

        $delivery = $this->delivery($rider, $order, [
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 170,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'amount_received' => 170,
            'signature_name' => 'COD Recipient',
            'otp' => $this->otpFor($delivery),
        ])->assertOk();

        $fresh = $delivery->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertSame(170.0, (float) $fresh->cod_amount_received);
        $this->assertNotNull($fresh->cod_received_at);

        $this->assertDatabaseHas('remittances', [
            'delivery_id' => $delivery->id,
            'amount' => 120,
            'status' => 'pending',
        ]);

        $audit = AuditLog::where('action', 'cod.collected')
            ->where('entity', 'delivery')
            ->where('entity_id', $delivery->id)
            ->firstOrFail();

        $this->assertSame(170.0, (float) $audit->meta['new_values']['amount_to_collect']);
        $this->assertSame(170.0, (float) $audit->meta['new_values']['amount_received']);
        $this->assertSame(0.0, (float) $audit->meta['new_values']['variance']);
    }

    public function test_cod_completion_accepts_a_short_payment_and_flags_the_variance(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $order->update(['total' => 170, 'total_amount' => 170, 'shipping_fee' => 50]);

        $delivery = $this->delivery($rider, $order, [
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 170,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'amount_received' => 165,
            'signature_name' => 'Short COD Recipient',
            'otp' => $this->otpFor($delivery),
        ])->assertOk();

        $fresh = $delivery->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertSame(165.0, (float) $fresh->cod_amount_received);
        $this->assertNotNull($fresh->cod_received_at);

        $remittance = Remittance::where('delivery_id', $delivery->id)->firstOrFail();
        $reconciliation = Cod::reconcile($remittance);

        $this->assertSame('variance', $reconciliation['state']);
        $this->assertSame(170.0, $reconciliation['expected']);
        $this->assertSame(165.0, $reconciliation['received']);
        $this->assertSame(-5.0, $reconciliation['variance']);

        $audit = AuditLog::where('action', 'cod.collected')
            ->where('entity', 'delivery')
            ->where('entity_id', $delivery->id)
            ->firstOrFail();

        $this->assertSame(165.0, (float) $audit->meta['new_values']['amount_received']);
        $this->assertSame(-5.0, (float) $audit->meta['new_values']['variance']);
    }

    public function test_cod_completion_accepts_an_excess_payment_and_flags_the_variance(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $order->update(['total' => 170, 'total_amount' => 170, 'shipping_fee' => 50]);

        $delivery = $this->delivery($rider, $order, [
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 170,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'amount_received' => 180,
            'signature_name' => 'Excess COD Recipient',
            'otp' => $this->otpFor($delivery),
        ])->assertOk();

        $fresh = $delivery->fresh();
        $this->assertSame('delivered', $fresh->status);
        $this->assertSame(180.0, (float) $fresh->cod_amount_received);

        $remittance = Remittance::where('delivery_id', $delivery->id)->firstOrFail();
        $reconciliation = Cod::reconcile($remittance);

        $this->assertSame('variance', $reconciliation['state']);
        $this->assertSame(170.0, $reconciliation['expected']);
        $this->assertSame(180.0, $reconciliation['received']);
        $this->assertSame(10.0, $reconciliation['variance']);

        $audit = AuditLog::where('action', 'cod.collected')
            ->where('entity', 'delivery')
            ->where('entity_id', $delivery->id)
            ->firstOrFail();

        $this->assertSame(180.0, (float) $audit->meta['new_values']['amount_received']);
        $this->assertSame(10.0, (float) $audit->meta['new_values']['variance']);
    }

    public function test_cod_completion_rejects_invalid_cash_amounts_before_any_writes(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $delivery = $this->delivery($rider, $order, [
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 170,
        ]);

        Sanctum::actingAs($user);
        $otp = $this->otpFor($delivery);

        foreach ([-1, 'not-a-number'] as $invalidAmount) {
            $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
                'amount_received' => $invalidAmount,
                'otp' => $otp,
            ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('amount_received');
        }

        $fresh = $delivery->fresh();
        $this->assertSame('out_for_delivery', $fresh->status);
        $this->assertNull($fresh->cod_amount_received);
        $this->assertNull($fresh->cod_received_at);
        $this->assertSame(0, AuditLog::where('action', 'cod.collected')->count());
        $this->assertSame([
            'transactions' => 0,
            'remittances' => 0,
            'earnings' => 0,
        ], $this->financialCounts($delivery));
    }

    public function test_cod_completion_rolls_back_the_cash_record_when_settlement_fails(): void
    {
        [$user, $rider] = $this->riderUser();
        $delivery = $this->delivery($rider, null, [
            'order_id' => 999999999,
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 170,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'amount_received' => 170,
            'proof_type' => 'signature',
        ])
            ->assertStatus(500)
            ->assertJsonPath('message', 'Delivery completion failed. No changes were saved.');

        $fresh = $delivery->fresh();
        $this->assertSame('out_for_delivery', $fresh->status);
        $this->assertNull($fresh->delivered_at);
        $this->assertNull($fresh->cod_amount_received);
        $this->assertNull($fresh->cod_received_at);
        $this->assertSame(0, AuditLog::where('action', 'cod.collected')->count());
        $this->assertSame([
            'transactions' => 0,
            'remittances' => 0,
            'earnings' => 0,
        ], $this->financialCounts($delivery));
    }

    public function test_repeating_cod_completion_does_not_duplicate_the_cash_record(): void
    {
        [$user, $rider] = $this->riderUser();
        [$order] = $this->order();
        $order->update(['total' => 170, 'total_amount' => 170, 'shipping_fee' => 50]);
        $delivery = $this->delivery($rider, $order, [
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 170,
        ]);
        $url = "/api/logistics/rider/deliveries/{$delivery->id}/complete";

        Sanctum::actingAs($user);

        $this->postJson($url, [
            'amount_received' => 170,
            'otp' => $this->otpFor($delivery),
        ])->assertOk();

        $this->postJson($url, ['amount_received' => 170])->assertStatus(409);

        $this->assertSame(170.0, (float) $delivery->fresh()->cod_amount_received);
        $this->assertSame(1, AuditLog::where('action', 'cod.collected')
            ->where('entity_id', $delivery->id)
            ->count());
        $this->assertSame([
            'transactions' => 1,
            'remittances' => 1,
            'earnings' => 1,
        ], $this->financialCounts($delivery));
    }

    public function test_another_rider_cannot_complete_a_cod_delivery(): void
    {
        [, $rider] = $this->riderUser();
        [$intruder] = $this->riderUser();
        [$order] = $this->order();
        $delivery = $this->delivery($rider, $order, [
            'payment_method' => 'cash_on_delivery',
            'amount_to_collect' => 170,
        ]);

        Sanctum::actingAs($intruder);

        $this->postJson("/api/logistics/rider/deliveries/{$delivery->id}/complete", [
            'amount_received' => 170,
        ])->assertForbidden();

        $fresh = $delivery->fresh();
        $this->assertSame('out_for_delivery', $fresh->status);
        $this->assertNull($fresh->cod_amount_received);
        $this->assertSame(0, AuditLog::where('action', 'cod.collected')->count());
        $this->assertSame([
            'transactions' => 0,
            'remittances' => 0,
            'earnings' => 0,
        ], $this->financialCounts($delivery));
    }

}
