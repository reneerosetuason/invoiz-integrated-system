<?php

namespace Tests\Feature;

use App\Models\{DeliveryFailure, DeliveryStatusLog, FailedDeliveryCase, FailedDeliveryFee, PayoutAccount, User};
use App\Services\FailedDeliveryService;
use App\Support\ReturnServiceEarnings;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema, Storage};
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FailedDeliveryResolutionTest extends TestCase
{
    use \Tests\Support\FailedDeliveryScenario { setUp as private baseSetUp; }

    private User $seller;
    private User $admin;
    private FailedDeliveryService $service;

    protected function setUp(): void
    {
        $this->baseSetUp();
        $this->assertSame('sqlite', DB::getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        // Only this new additive migration, inside the already guarded fixture.
        (require __DIR__.'/../../database/migrations/2026_11_15_220000_create_failed_delivery_resolutions.php')->up();
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->string('transaction_ref')->nullable();
            $table->string('gateway')->nullable(); $table->decimal('amount', 12, 2)->default(0);
            $table->string('status')->default('pending'); $table->decimal('refunded_amount', 12, 2)->default(0); $table->timestamps();
        });
        Schema::create('payout_accounts', function (Blueprint $table) {
            $table->id(); $table->string('owner_type'); $table->unsignedBigInteger('owner_id');
            $table->text('details'); $table->timestamps(); $table->unique(['owner_type', 'owner_id']);
        });
        Storage::fake('private');
        Schema::table('orders', function (Blueprint $table) { $table->text('cancellation_reason')->nullable(); });
        Schema::create('products', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('seller_id'); $table->string('name');
            $table->integer('stock')->default(10); $table->decimal('price', 10, 2)->default(100);
            $table->string('status')->default('active'); $table->timestamps(); $table->softDeletes();
        });
        Schema::create('order_items', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('product_id'); $table->unsignedBigInteger('product_variant_id')->nullable();
            $table->unsignedBigInteger('variant_id')->nullable(); $table->string('product_name')->nullable();
            $table->integer('quantity')->default(1); $table->decimal('price', 10, 2)->default(100);
            $table->decimal('unit_price', 10, 2)->default(100); $table->decimal('total_price', 10, 2)->default(100); $table->timestamps();
        });
        Schema::create('order_vouchers', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('order_id'); $table->unsignedBigInteger('voucher_id'); $table->decimal('discount_amount', 10, 2)->default(0);
        });
        $this->seller = $this->identity('seller', 'seller@example.test');
        $this->admin = $this->identity('admin', 'platform.admin@example.test');
        $this->admin->forceFill(['is_admin' => true])->save();
        $this->delivery->update(['seller_id' => $this->seller->id, 'status' => 'delivery_failed',
            'returned_at' => now(), 'parcel_status' => 'sorted']);
        $this->delivery->order->update(['seller_id' => $this->seller->id, 'payment_status' => 'pending']);
        DB::table('products')->insert(['id' => 1, 'seller_id' => $this->seller->id, 'name' => 'Synthetic Parcel', 'stock' => 10]);
        \App\Models\OrderItem::create(['order_id' => $this->delivery->order_id, 'seller_id' => $this->seller->id,
            'product_id' => 1, 'product_name' => 'Synthetic Parcel', 'quantity' => 1, 'unit_price' => 100]);
        for ($i = 1; $i <= 3; $i++) {
            DeliveryFailure::create(['delivery_id' => $this->delivery->id, 'rider_id' => $this->rider->id,
                'reason' => 'Customer unavailable', 'reported_at' => now()]);
            DeliveryStatusLog::create(['delivery_id' => $this->delivery->id, 'status' => 'delivery_failed']);
        }
        $this->service = app(FailedDeliveryService::class);
    }

    private function open(): FailedDeliveryCase { return $this->service->open($this->delivery->fresh()); }

    private function act($actor, string $action, array $data = []): FailedDeliveryCase
    {
        return $this->service->act($this->delivery->fresh(), $actor, $action, $data);
    }

    private function chooseUnclaimed(): FailedDeliveryCase
    {
        $this->open(); return $this->act($this->seller, 'decide', ['decision' => 'unclaimed', 'confirm_unclaimed' => true]);
    }

    private function fund(): FailedDeliveryCase
    {
        Storage::disk('private')->put('synthetic-receipt.png', 'synthetic receipt fixture');
        $case = $this->act($this->seller, 'deposit', ['reference' => 'SYNTHETIC-DEPOSIT-1', 'proof_path' => 'synthetic-receipt.png']);
        $payment = $case->payments()->firstOrFail();
        $this->assertNull($case->funded_at);
        $payment->update(['receipt_viewed_by' => $this->admin->id, 'receipt_viewed_at' => now()]);
        return $this->act($this->admin, 'review_payment', ['payment_id' => $payment->id, 'approved' => true, 'confirm_verified' => true]);
    }

    public function test_resolution_is_opened_once_and_charges_only_one_outbound_last_mile_fee(): void
    {
        $case = $this->open(); $again = $this->open();
        $this->assertSame($case->id, $again->id);
        $this->assertSame(1, FailedDeliveryCase::count());
        $this->assertSame(5000, (int) $case->fees()->where('role', 'last_mile_rider')->sum('amount_cents'));
        $this->assertSame(0, $case->fees()->where('leg', 'return')->count());
        $this->assertSame('out_for_delivery', $this->delivery->fresh()->order->status);
    }

    public function test_unclaimed_closes_fulfillment_without_restock_or_an_extra_return_trip(): void
    {
        $case = $this->chooseUnclaimed();
        $this->assertSame('unclaimed', $case->stage);
        $this->assertNotNull($case->closed_at);
        $this->assertNull($case->funded_at);
        $this->assertSame($this->hub->id, $case->center_id);
        $this->assertSame('delivery_failed', $this->delivery->fresh()->status);
        $this->assertSame('cancelled', $this->delivery->fresh()->order->status);
        $this->assertSame(0, $case->fees()->where('leg', 'return')->count());
        $this->assertGreaterThan(0, $this->service->outstanding($case));
        $this->assertSame(0, DeliveryStatusLog::where('status', 'return_received_by_seller')->count());
        $this->assertSame(10, DB::table('products')->value('stock'));
    }

    public function test_a_receipt_is_not_funding_until_the_admin_views_and_verifies_it(): void
    {
        $case = $this->chooseUnclaimed();
        Storage::disk('private')->put('receipt.png', 'synthetic');
        $case = $this->act($this->seller, 'deposit', ['reference' => 'RECEIPT-ONLY', 'proof_path' => 'receipt.png']);
        $this->assertNull($case->funded_at);
        $payment = $case->payments()->firstOrFail();
        try {
            $this->act($this->admin, 'review_payment', ['payment_id' => $payment->id, 'approved' => true, 'confirm_verified' => true]);
            $this->fail('An unopened receipt cannot be verified.');
        } catch (ValidationException $e) {
            $this->assertNull($case->fresh()->funded_at);
            $this->assertSame('submitted', $payment->fresh()->status);
        }
    }

    public function test_seller_cannot_verify_their_own_deposit(): void
    {
        $case = $this->chooseUnclaimed();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->act($this->seller, 'review_payment', ['payment_id' => 1, 'approved' => true]);
    }

    public function test_completed_services_remain_payable_after_unclaimed_and_acceptance_is_idempotent(): void
    {
        $case = $this->chooseUnclaimed(); $funded = $this->fund();
        $this->assertNotNull($funded->funded_at);
        $fee = $case->fees()->where('role', 'last_mile_rider')->firstOrFail();
        PayoutAccount::create(['owner_type' => 'user', 'owner_id' => $this->riderUser->id,
            'details' => ['provider' => 'Synthetic bank', 'holder' => 'Synthetic Rider', 'account' => 'TEST-ACCOUNT-0001']]);
        Storage::disk('private')->put('payout.png', 'synthetic payout');
        $this->assertSame(0.0, ReturnServiceEarnings::amount(ReturnServiceEarnings::rider($this->rider)));
        $this->service->payout($fee, $this->admin, false, ['reference' => 'OUTGOING-SYNTHETIC-1', 'proof_path' => 'payout.png']);
        $this->assertSame('remitted', $fee->fresh()->status);
        $this->assertSame(0.0, ReturnServiceEarnings::amount(ReturnServiceEarnings::rider($this->rider)));
        $this->service->payout($fee, $this->riderUser, true);
        $this->service->payout($fee, $this->riderUser, true);
        $this->assertSame('accepted', $fee->fresh()->status);
        $this->assertSame(50.0, ReturnServiceEarnings::amount(ReturnServiceEarnings::rider($this->rider)));
    }

    public function test_return_is_quoted_separately_and_requires_seller_acceptance_and_funding(): void
    {
        $this->open(); $this->act($this->seller, 'decide', ['decision' => 'return']);
        $case = $this->act($this->staff, 'quote', ['seller_center_id' => $this->hub->id]);
        $this->assertSame('awaiting_acceptance', $case->stage);
        $this->assertGreaterThan(0, $case->fees()->where('leg', 'return')->sum('amount_cents'));
        $case = $this->act($this->seller, 'accept_quote', ['version' => $case->quote['version'], 'confirm_quote' => true]);
        $this->assertSame('awaiting_payment', $case->stage);
        try {
            $this->act($this->staff, 'assign_rider', ['rider_id' => $this->rider->id]);
            $this->fail('Unfunded reverse transport must not start.');
        } catch (ValidationException $e) { $this->assertNull($case->fresh()->return_rider_id); }
        $case = $this->fund();
        $this->assertSame('at_hub', $case->stage);
        $this->assertNull($case->fees()->where('leg', 'return')->where('role', 'last_mile_rider')->first()->fulfilled_at);
    }

    public function test_only_the_seller_can_choose_unclaimed(): void
    {
        $this->open();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->act($this->staff, 'decide', ['decision' => 'unclaimed', 'confirm_unclaimed' => true]);
    }

    public function test_payment_references_cannot_be_reused_for_a_worker_payout(): void
    {
        $this->chooseUnclaimed(); $case = $this->fund();
        $fee = $case->fees()->where('role', 'last_mile_rider')->firstOrFail();
        PayoutAccount::create(['owner_type' => 'user', 'owner_id' => $this->riderUser->id,
            'details' => ['provider' => 'Synthetic bank', 'holder' => 'Synthetic Rider', 'account' => 'TEST-ACCOUNT-0001']]);
        Storage::disk('private')->put('payout.png', 'synthetic');
        $this->expectException(ValidationException::class);
        $this->service->payout($fee, $this->admin, false, ['reference' => 'SYNTHETIC-DEPOSIT-1', 'proof_path' => 'payout.png']);
    }

    public function test_same_hub_return_requires_collection_photo_and_seller_receipt_before_restock(): void
    {
        $this->open(); $this->act($this->seller, 'decide', ['decision' => 'return']);
        $case = $this->act($this->staff, 'quote', ['seller_center_id' => $this->hub->id]);
        $this->act($this->seller, 'accept_quote', ['version' => $case->quote['version'], 'confirm_quote' => true]);
        $this->fund();
        $this->act($this->staff, 'assign_rider', ['rider_id' => $this->rider->id]);
        $this->act($this->riderUser, 'rider_collect');
        $this->assertSame(10, DB::table('products')->value('stock'));
        Storage::disk('private')->put('seller-handover.jpg', 'synthetic handover');
        $this->act($this->riderUser, 'seller_arrival', ['proof_path' => 'seller-handover.jpg']);
        $this->assertSame(10, DB::table('products')->value('stock'));
        $case = $this->act($this->seller, 'seller_receive');
        $this->assertSame('completed', $case->stage);
        $this->assertSame('returned_to_seller', $this->delivery->fresh()->status);
        $this->assertSame(11, DB::table('products')->value('stock'));
        $this->act($this->seller, 'seller_receive');
        $this->assertSame(11, DB::table('products')->value('stock'));
        $this->assertNotNull($case->fees()->where('service_key', 'return:last_mile_rider')->first()->fulfilled_at);
    }

    public function test_seller_cannot_confirm_receipt_before_the_riders_handover_photo(): void
    {
        $this->open(); $this->act($this->seller, 'decide', ['decision' => 'return']);
        $case = $this->act($this->staff, 'quote', ['seller_center_id' => $this->hub->id]);
        $this->act($this->seller, 'accept_quote', ['version' => $case->quote['version'], 'confirm_quote' => true]);
        $this->fund();
        $this->expectException(ValidationException::class);
        $this->act($this->seller, 'seller_receive');
    }

    public function test_prepaid_unclaimed_uses_existing_refund_review_without_restocking(): void
    {
        Schema::create('return_requests', function (Blueprint $table) {
            $table->id();
            foreach (['order_id', 'order_item_id', 'buyer_id', 'seller_id', 'product_id'] as $key) $table->unsignedBigInteger($key)->nullable();
            $table->integer('quantity')->default(0); $table->string('reason'); $table->text('reason_details')->nullable();
            $table->decimal('refund_amount', 10, 2)->default(0); $table->string('status'); $table->text('decision_note')->nullable(); $table->timestamps();
        });
        (require database_path('migrations/2026_11_05_180000_create_return_workflows.php'))->up();
        $this->delivery->order->update(['payment_status' => 'paid']);
        $this->chooseUnclaimed();
        $this->assertSame('paid', $this->delivery->fresh()->order->payment_status);
        $this->assertSame(10, DB::table('products')->value('stock'));
        $this->assertSame('refund_pending', DB::table('return_workflows')->value('stage'));
        $this->assertSame(0, (int) DB::table('return_requests')->value('quantity'));
        $this->assertSame(300.0, (float) DB::table('return_requests')->sum('refund_amount'));
    }

    public function test_inter_hub_return_requires_driver_arrival_and_receiving_staff_before_seller_delivery(): void
    {
        Schema::create('hub_transport_drivers', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('user_id')->nullable(); $table->unsignedBigInteger('logistics_company_id');
            $table->unsignedBigInteger('center_id')->nullable(); $table->unsignedBigInteger('logistics_truck_id');
            $table->string('name'); $table->string('email')->nullable(); $table->string('status'); $table->timestamps(); $table->softDeletes();
            $table->boolean('is_online')->default(true);
        });
        Schema::create('logistics_trucks', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('logistics_company_id'); $table->unsignedBigInteger('center_id')->nullable();
            $table->string('vehicle_type'); $table->string('verification_status'); $table->string('status');
            $table->decimal('capacity_kg', 10, 2); $table->unsignedInteger('parcel_capacity'); $table->timestamps(); $table->softDeletes();
        });
        $sellerHub = \App\Models\LogisticsCenter::create(['name' => 'Seller Hub', 'is_active' => true,
            'logistics_company_id' => $this->hub->logistics_company_id]);
        $receivingStaff = $this->identity('staff', 'receiving@example.test');
        $receivingStaff->update(['center_id' => $sellerHub->id, 'logistics_company_id' => $this->hub->logistics_company_id]);
        $truck = \App\Models\LogisticsTruck::create(['logistics_company_id' => $this->hub->logistics_company_id,
            'center_id' => $this->hub->id, 'vehicle_type' => 'truck', 'verification_status' => 'approved',
            'status' => 'available', 'capacity_kg' => 100, 'parcel_capacity' => 20]);
        $driver = \App\Models\HubTransportDriver::create(['logistics_company_id' => $this->hub->logistics_company_id,
            'center_id' => $this->hub->id, 'logistics_truck_id' => $truck->id, 'name' => 'Synthetic Driver', 'status' => 'available']);
        $this->mock(\App\Services\HubRoadDistanceService::class)->shouldReceive('between')->once()
            ->andReturn(['distance' => 12.5, 'source' => 'synthetic route']);
        $this->open(); $this->act($this->seller, 'decide', ['decision' => 'return']);
        $case = $this->act($this->staff, 'quote', ['seller_center_id' => $sellerHub->id, 'truck_id' => $truck->id]);
        $this->assertGreaterThan(0, $case->fees()->where('leg', 'return')->where('role', 'inter_hub_driver')->value('amount_cents'));
        $this->act($this->seller, 'accept_quote', ['version' => $case->quote['version'], 'confirm_quote' => true]);
        $this->fund();
        $this->act($driver, 'driver_accept');
        $this->act($driver, 'depart');
        $this->assertTrue($driver->fresh()->hasActiveTransfer());
        $this->assertTrue($truck->fresh()->hasActiveTransfer());
        try { $this->act($receivingStaff, 'receive'); $this->fail('Arrival without handover cannot transfer custody.'); }
        catch (ValidationException $e) { $this->assertSame('in_transfer', $case->fresh()->stage); }
        Storage::disk('private')->put('hub-handover.jpg', 'synthetic hub handover');
        $this->act($driver, 'arrive', ['proof_path' => 'hub-handover.jpg']);
        try { $this->act($this->staff, 'receive'); $this->fail('Only receiving-hub staff can accept custody.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame('hub_arrival', $case->fresh()->stage); }
        $case = $this->act($receivingStaff, 'receive');
        $this->assertSame('at_seller_hub', $case->stage);
        $this->assertSame($sellerHub->id, $case->center_id);
        $this->assertNotNull($case->fees()->where('service_key', 'return:inter_hub_driver')->first()->fulfilled_at);
        $this->assertFalse($driver->fresh()->hasActiveTransfer());
    }

    private function escrowFixture(): void
    {
        Schema::create('escrow_remittances', function (Blueprint $table) {
            $table->id();
            foreach (['order_id', 'seller_id', 'delivery_id', 'payment_transaction_id', 'recipient_user_id', 'rider_id', 'hub_transfer_id', 'hub_transport_driver_id', 'logistics_center_id', 'remitted_by'] as $key) $table->unsignedBigInteger($key)->nullable();
            foreach (['settlement_reference', 'recipient_role', 'recipient_name', 'rule_applied', 'settlement_mode', 'status', 'bank_reference'] as $key) $table->string($key)->nullable();
            $table->text('notes')->nullable(); $table->decimal('amount', 12, 2)->default(0); $table->timestamp('remitted_at')->nullable(); $table->timestamps();
        });
    }

    public function test_paid_outbound_services_are_credited_instead_of_billed_twice(): void
    {
        $this->escrowFixture();
        $paid = \App\Models\EscrowRemittance::create(['delivery_id' => $this->delivery->id,
            'recipient_role' => 'pickup_rider', 'rider_id' => $this->rider->id, 'amount' => 30, 'status' => 'accepted']);
        $case = $this->open();
        $fee = $case->fees()->where('role', 'pickup_rider')->firstOrFail();
        $this->assertSame(3000, (int) $fee->credited_cents);
        $this->assertSame(0, $fee->payableCents());
        $this->assertSame('accepted', $paid->fresh()->status);
        $this->assertSame(8000, $this->service->outstanding($case));
    }

    public function test_unpaid_worker_allocations_move_without_touching_buyer_or_seller_money(): void
    {
        $this->escrowFixture();
        $worker = \App\Models\EscrowRemittance::create(['delivery_id' => $this->delivery->id,
            'recipient_role' => 'pickup_rider', 'rider_id' => $this->rider->id, 'amount' => 30, 'status' => 'held_in_escrow']);
        $seller = \App\Models\EscrowRemittance::create(['delivery_id' => $this->delivery->id,
            'recipient_role' => 'seller', 'recipient_user_id' => $this->seller->id, 'amount' => 100, 'status' => 'held_in_escrow']);
        $this->open();
        $this->assertSame('voided', $worker->fresh()->status);
        $this->assertSame('held_in_escrow', $seller->fresh()->status);
        $this->assertSame('100.00', $seller->fresh()->amount);
    }

    public function test_attempt_riders_share_one_last_mile_budget_without_rounding_loss(): void
    {
        $otherUser = $this->identity('rider', 'second.attempt@example.test');
        $other = $this->eligibleRider($otherUser);
        DeliveryFailure::where('delivery_id', $this->delivery->id)->latest('id')->first()->update(['rider_id' => $other->id]);
        $case = $this->open();
        $fees = $case->fees()->where('role', 'last_mile_rider')->get();
        $this->assertCount(2, $fees);
        $this->assertSame(5000, (int) $fees->sum('amount_cents'));
        $this->assertSame(3333, (int) $fees->firstWhere('rider_id', $this->rider->id)->amount_cents);
        $this->assertSame(1667, (int) $fees->firstWhere('rider_id', $other->id)->amount_cents);
    }

    public function test_corrected_rejected_receipt_keeps_one_reference_and_requires_a_new_preview(): void
    {
        $this->chooseUnclaimed();
        Storage::disk('private')->put('rejected.png', 'synthetic first receipt');
        $case = $this->act($this->seller, 'deposit', ['reference' => 'CORRECTED-REF-1', 'proof_path' => 'rejected.png']);
        $payment = $case->payments()->first();
        $this->act($this->admin, 'review_payment', ['payment_id' => $payment->id, 'approved' => false]);
        Storage::disk('private')->put('corrected.png', 'synthetic corrected receipt');
        $case = $this->act($this->seller, 'deposit', ['reference' => 'CORRECTED-REF-1', 'proof_path' => 'corrected.png']);
        $this->assertSame(1, $case->payments()->count());
        $this->assertNull($payment->fresh()->receipt_viewed_at);
        $this->assertCount(1, $payment->fresh()->revisions);
        $this->expectException(ValidationException::class);
        $this->act($this->admin, 'review_payment', ['payment_id' => $payment->id, 'approved' => true, 'confirm_verified' => true]);
    }

    public function test_unrelated_seller_cannot_read_the_case_or_private_receipt(): void
    {
        $case = $this->chooseUnclaimed();
        Storage::disk('private')->put('private-receipt.png', 'synthetic receipt');
        $case = $this->act($this->seller, 'deposit', ['reference' => 'PRIVATE-REF-1', 'proof_path' => 'private-receipt.png']);
        $outsider = $this->identity('seller', 'unrelated.store@example.test');
        \Laravel\Sanctum\Sanctum::actingAs($outsider);
        $this->getJson('/api/failed-deliveries/'.$this->delivery->id)->assertForbidden();
        $this->actingAs($outsider, 'web')->get(route('failed-deliveries.proof', ['deposit', $case->payments()->first()->id]))->assertForbidden();
    }

    public function test_existing_incoming_payment_api_accepts_the_new_fee_source_once(): void
    {
        $case = $this->chooseUnclaimed(); $this->fund();
        $fee = $case->fees()->where('role', 'last_mile_rider')->first();
        PayoutAccount::create(['owner_type' => 'user', 'owner_id' => $this->riderUser->id,
            'details' => ['provider' => 'Synthetic bank', 'holder' => 'Synthetic Rider', 'account' => 'TEST-ACCOUNT-0001']]);
        Storage::disk('private')->put('inbox-transfer.png', 'synthetic outgoing receipt');
        $this->service->payout($fee, $this->admin, false, ['reference' => 'INBOX-PAYOUT-1', 'proof_path' => 'inbox-transfer.png']);
        \Laravel\Sanctum\Sanctum::actingAs($this->riderUser, ['rider']);
        $response = $this->getJson('/api/remittances/incoming')->assertOk();
        $incoming = collect($response->json('remittances'))->firstWhere('id', $fee->id);
        $this->assertSame('failed_delivery', $incoming['source']);
        $this->assertTrue($incoming['can_accept']);
        $this->postJson('/api/remittances/incoming/failed_delivery/'.$fee->id.'/accept', ['confirm_received' => true])->assertOk();
        $this->postJson('/api/remittances/incoming/failed_delivery/'.$fee->id.'/accept', ['confirm_received' => true])->assertOk();
        $this->assertSame('accepted', $fee->fresh()->status);
        $this->assertSame(50.0, ReturnServiceEarnings::amount(ReturnServiceEarnings::rider($this->rider)));
    }
}
