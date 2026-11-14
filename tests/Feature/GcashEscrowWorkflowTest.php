<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\EscrowRemittance;
use App\Models\GcashEscrowDeposit;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Rider;
use App\Models\RiderEarning;
use App\Models\Seller;
use App\Models\User;
use App\Services\GcashEscrowService;
use App\Support\EscrowEarnings;
use App\Support\Payments;
use App\Support\Remittances;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuyerMobileDatabase;
use Tests\TestCase;

class GcashEscrowWorkflowTest extends TestCase
{
    use BuyerMobileDatabase;

    private User $seller;

    private User $buyer;

    private User $admin;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createMobileDatabase();
        (require database_path('migrations/2026_11_09_080000_create_payout_accounts.php'))->up();
        (require database_path('migrations/2026_11_04_120000_add_proof_path_to_payments_table.php'))->up();
        (require database_path('migrations/2026_11_04_140000_add_gcash_escrow_workflow.php'))->up();
        (require database_path('migrations/2026_10_31_110000_create_earnings_and_escrow_allocations.php'))->up();
        Schema::table('payments', fn (Blueprint $t) => $t->timestamp('paid_at')->nullable());
        Schema::table('deliveries', function (Blueprint $t) {
            $t->timestamp('received_at')->nullable();
            $t->unsignedBigInteger('sorting_center_handoff_rider_id')->nullable();
        });
        Schema::create('riders', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id');
            $t->string('name');
            $t->string('status')->default('active');
            $t->boolean('is_verified')->default(true);
            $t->timestamp('approved_at')->useCurrent();
            $t->unsignedBigInteger('center_id')->nullable();
            $t->timestamps();
        });
        Schema::create('rider_earnings', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('rider_id');
            $t->unsignedBigInteger('delivery_id');
            $t->string('type');
            $t->decimal('amount', 12, 2);
            $t->date('earned_on');
            $t->string('description')->nullable();
            $t->timestamps();
            $t->unique(['delivery_id', 'type']);
        });
        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('action');
            $t->string('entity');
            $t->unsignedBigInteger('entity_id');
            $t->json('meta')->nullable();
            $t->timestamps();
        });
        Schema::create('payment_disputes', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('order_id');
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('seller_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('seller_id');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->string('type');
            $t->string('title');
            $t->text('body');
            $t->timestamps();
        });
        foreach (['seller_payouts', 'withdrawal_requests'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('seller_id');
                $t->decimal('amount', 12, 2);
                $t->string('status');
                $t->timestamps();
            });
        }
        Storage::fake('private');
        Storage::fake('public');
        $this->seller = $this->user('seller');
        $this->buyer = $this->user('buyer');
        $this->admin = $this->user('admin', true);
        Seller::create(['user_id' => $this->seller->id, 'business_name' => 'Shop', 'status' => 'approved', 'approval_status' => 'approved']);
        $this->order = Order::create(['seller_id' => $this->seller->id, 'buyer_id' => $this->buyer->id,
            'status' => 'pending', 'total_amount' => 150, 'sub_total' => 100, 'shipping_fee' => 50]);
        OrderItem::create(['order_id' => $this->order->id, 'seller_id' => $this->seller->id, 'product_name' => 'Item', 'quantity' => 1, 'price' => 100, 'unit_price' => 100, 'total_price' => 100]);
        Storage::disk('private')->put('payment-proofs/buyer.png', 'buyer receipt');
        Payment::create(['order_id' => $this->order->id, 'method' => 'gcash', 'amount' => 150, 'status' => 'pending', 'proof_path' => 'payment-proofs/buyer.png']);
        Delivery::create(['order_id' => $this->order->id, 'seller_id' => $this->seller->id, 'status' => 'waiting_for_rider', 'payment_method' => 'gcash', 'amount_to_collect' => 0]);
        Payments::recordCheckout($this->order);
        DB::table('gcash_escrow_settings')->insert(['account_name' => 'Platform', 'account_details' => 'Test account', 'created_at' => now(), 'updated_at' => now()]);
        $this->withSession(['active_role' => 'seller']);
    }

    protected function saveRecipientAccount(\App\Models\EscrowRemittance|\App\Models\ReturnAllocation $line): void
    {
        $target = \App\Support\PayoutAccounts::forRemittance($line);
        if (!$target) return;
        [$type, $id] = explode('-', $target['key'], 2);
        \App\Models\PayoutAccount::updateOrCreate(['owner_type' => $type, 'owner_id' => (int) $id],
            ['details' => ['provider' => 'Test bank', 'holder' => 'Test recipient', 'account' => '000123456789']]);
    }

    private function user(string $role, bool $admin = false): User
    {
        $user = User::create(['name' => 'Test '.$role, 'email' => uniqid().'@example.test', 'password' => 'Password123!',
            'role' => $role, 'is_admin' => $admin, 'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved', 'email_verified_at' => now()]);
        \App\Models\PayoutAccount::create(['owner_type' => 'user', 'owner_id' => $user->id,
            'details' => ['provider' => 'Test bank', 'holder' => $user->name, 'account' => '000123456789']]);
        return $user;
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('receipt.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Zl1sAAAAASUVORK5CYII='));
    }

    private function submit(): GcashEscrowDeposit
    {
        $this->actingAs($this->seller)->post(route('seller.gcash.verify', $this->order), ['confirm_received' => 1])->assertSessionHasNoErrors();
        $this->post(route('seller.gcash.deposit', $this->order), ['amount' => 150, 'reference' => 'TEST-ORDER-'.$this->order->id, 'escrow_proof' => $this->image()])->assertSessionHasNoErrors();

        return GcashEscrowDeposit::firstOrFail();
    }

    private function fund(): void
    {
        $deposit = $this->submit();
        $this->actingAs($this->admin)->post(route('admin.gcash.review', $deposit), ['decision' => 'approve', 'note' => 'Matched full incoming transfer in test account.', 'verified_in_account' => 1])->assertSessionHasNoErrors();
        $this->assertSame('funded', $deposit->fresh()->status);
    }

    public function test_seller_cannot_accept_with_only_a_buyer_receipt_or_submitted_deposit(): void
    {
        $this->actingAs($this->seller)->post(route('seller.orders.status', $this->order), ['action' => 'accept'])->assertSessionHasErrors('gcash');
        $this->submit();
        $this->post(route('seller.orders.status', $this->order), ['action' => 'accept'])->assertSessionHasErrors('gcash');
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame('pending', $this->order->payment->fresh()->status);
    }

    public function test_missing_platform_account_blocks_seller_deposit_and_acceptance_after_receipt_verification(): void
    {
        DB::table('gcash_escrow_settings')->delete();
        $this->actingAs($this->seller)->post(route('seller.gcash.verify', $this->order), ['confirm_received' => 1])->assertSessionHasNoErrors();
        $this->post(route('seller.gcash.deposit', $this->order), [
            'amount' => 150, 'reference' => 'NO-PLATFORM-ACCOUNT', 'escrow_proof' => $this->image(),
        ])->assertSessionHasErrors('gcash');
        $this->post(route('seller.orders.status', $this->order), ['action' => 'accept'])->assertSessionHasErrors('gcash');
        $this->assertSame('receipt_verified', GcashEscrowDeposit::firstOrFail()->status);
        $this->assertSame('pending', $this->order->fresh()->status);
        $this->assertSame('pending', $this->order->payment->fresh()->status);
        $this->assertSame([], Storage::disk('private')->allFiles('escrow-deposit-proofs'));
    }

    public function test_verification_ownership_and_full_deposit_amount_are_enforced(): void
    {
        $this->actingAs($this->buyer)->post(route('seller.gcash.verify', $this->order), ['confirm_received' => 1])->assertRedirect();
        $otherSeller = $this->user('seller');
        Seller::create(['user_id' => $otherSeller->id, 'store_name' => 'Other shop', 'status' => 'approved', 'approval_status' => 'approved']);
        $this->actingAs($otherSeller)->post(route('seller.gcash.verify', $this->order), ['confirm_received' => 1])->assertNotFound();
        $this->actingAs($this->seller)->post(route('seller.gcash.verify', $this->order), ['confirm_received' => 1])->assertSessionHasNoErrors();
        $this->post(route('seller.gcash.deposit', $this->order), ['amount' => 100, 'reference' => 'PARTIAL', 'escrow_proof' => $this->image()])->assertSessionHasErrors('amount');
        $this->assertSame([], Storage::disk('private')->allFiles('escrow-deposit-proofs'));
        $this->assertSame('receipt_verified', GcashEscrowDeposit::first()->status);
    }

    public function test_admin_must_attest_actual_funds_and_only_admin_confirmation_unlocks_order(): void
    {
        $deposit = $this->submit();
        $this->actingAs($this->seller)->post(route('admin.gcash.review', $deposit), ['decision' => 'approve', 'note' => 'Fake', 'verified_in_account' => 1])->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.gcash.review', $deposit), ['decision' => 'approve', 'note' => 'Checked'])->assertSessionHasErrors('verified_in_account');
        $this->post(route('admin.gcash.review', $deposit), ['decision' => 'approve', 'note' => 'Verified incoming full amount', 'verified_in_account' => 1])->assertSessionHasNoErrors();
        $this->assertSame('paid', $this->order->payment->fresh()->status);
        $this->assertSame('pending', Payments::transactionFor($this->order)->status);
        $this->assertSame(0.0, Payments::availableBalanceForUser($this->seller->id));
        $this->assertSame(0, EscrowRemittance::whereIn('status', ['remitted', 'accepted', 'pending_remittance'])->count());
        $this->actingAs($this->seller)->withSession(['active_role' => 'seller'])->post(route('seller.orders.status', $this->order), ['action' => 'accept'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $this->order->fresh()->status);
    }

    public function test_rejected_deposit_can_be_corrected_and_review_cannot_be_replayed(): void
    {
        $deposit = $this->submit();
        $this->actingAs($this->admin)->post(route('admin.gcash.review', $deposit), ['decision' => 'reject', 'note' => 'No matching funds'])->assertSessionHasNoErrors();
        $this->assertFalse(GcashEscrowService::funded($this->order));
        $this->actingAs($this->seller)->post(route('seller.gcash.deposit', $this->order), ['amount' => 150, 'reference' => 'CORRECTED-TRANSFER', 'escrow_proof' => $this->image()])->assertSessionHasNoErrors();
        GcashEscrowService::confirm($deposit->fresh(), $this->admin, true, 'Matched');
        $this->actingAs($this->admin)->post(route('admin.gcash.review', $deposit), ['decision' => 'approve', 'note' => 'Replay', 'verified_in_account' => 1])->assertSessionHasErrors('gcash');
        $this->assertDatabaseCount('gcash_escrow_deposits', 1);
    }

    public function test_deposit_proof_is_private_and_only_admin_can_review_it(): void
    {
        $deposit = $this->submit();
        Storage::disk('public')->assertMissing($deposit->proof_path);
        $this->actingAs($this->buyer)->get(route('admin.gcash.proof', $deposit))->assertForbidden();
        $this->actingAs($this->seller)->get(route('admin.gcash.proof', $deposit))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.gcash.proof', $deposit))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_gcash_rider_gets_no_cash_handoff_and_earns_only_after_acceptance(): void
    {
        $this->fund();
        $riderUser = $this->user('rider');
        $rider = Rider::create(['user_id' => $riderUser->id, 'name' => 'Rider']);
        $delivery = $this->order->delivery;
        $delivery->update(['status' => 'delivered', 'rider_id' => $rider->id, 'delivered_at' => now()]);
        $this->assertNull(Remittances::ensureForDeliveryOrFail($delivery));
        $this->assertSame('successful', Payments::transactionFor($this->order)->status);
        $payout = EscrowRemittance::where('order_id', $this->order->id)->where('recipient_role', 'last_mile_rider')->firstOrFail();
        $this->assertSame('pending_remittance', $payout->status);
        $this->assertSame('escrow_bank_remittance', $payout->settlement_mode);
        $this->assertDatabaseCount('rider_earnings', 0);
        $this->actingAs($this->admin)->post(route('admin.escrow.remit', $payout), [])->assertSessionHasErrors('bank_reference');
        $this->post(route('admin.escrow.remit', $payout), ['bank_reference' => 'ACTUAL-PAYOUT-001'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rider_earnings', 0);
        Sanctum::actingAs($riderUser, ['rider']);
        $this->getJson('/api/logistics/rider/earnings')->assertOk()->assertJsonPath('today', 0);
        $this->getJson('/api/logistics/rider/remittances/earnings-payouts')->assertOk()->assertJsonPath('total_confirmed', 0);
        $this->postJson('/api/logistics/rider/remittances/'.$payout->id.'/accept-payout')->assertOk();
        $this->assertSame(50.0, (float) RiderEarning::where('rider_id', $rider->id)->sum('amount'));
        $this->getJson('/api/logistics/rider/earnings')->assertOk()->assertJsonPath('today', 50);
        $this->assertFalse($payout->fresh()->accept($riderUser));
        EscrowEarnings::syncRemittancesForOrder($this->order->fresh());
        $this->assertSame('accepted', $payout->fresh()->status);
        $this->assertDatabaseCount('rider_earnings', 1);
    }

    public function test_unfunded_gcash_cannot_be_dispatched_through_delivery_updates(): void
    {
        $this->expectException(ValidationException::class);
        $this->order->delivery->update(['status' => 'assigned']);
    }

    public function test_admin_cannot_batch_release_gcash_or_release_before_delivery(): void
    {
        $this->fund();
        $row = EscrowRemittance::where('order_id', $this->order->id)->where('recipient_role', 'seller')->firstOrFail();
        $row->update(['status' => 'pending_remittance']);
        $this->actingAs($this->admin)->post(route('admin.escrow.remit-all'))->assertRedirect();
        $this->assertSame('pending_remittance', $row->fresh()->status);
        $this->post(route('admin.escrow.remit', $row), ['bank_reference' => 'TEST-NOT-SENT'])->assertSessionHasErrors('gcash');
        $this->assertSame('pending_remittance', $row->fresh()->status);
    }

    public function test_legacy_gcash_earnings_are_hidden_until_the_matching_payout_is_accepted(): void
    {
        $this->fund();
        $user = $this->user('rider');
        $rider = Rider::create(['user_id' => $user->id, 'name' => 'Rider']);
        $delivery = $this->order->delivery;
        $delivery->update(['status' => 'delivered', 'rider_id' => $rider->id]);
        Remittances::ensureForDeliveryOrFail($delivery);
        RiderEarning::withoutGlobalScopes()->create(['delivery_id' => $delivery->id, 'rider_id' => $rider->id, 'type' => 'delivery', 'amount' => 50, 'earned_on' => now()->toDateString()]);
        $this->assertSame(0, RiderEarning::count());
        $payout = EscrowRemittance::where('recipient_role', 'last_mile_rider')->firstOrFail();
        $payout->update(['status' => 'remitted']);
        $this->assertSame(0, RiderEarning::count());
        $this->assertTrue($payout->accept($user));
        $this->assertSame(1, RiderEarning::count());
        $this->assertDatabaseCount('rider_earnings', 1);
    }

    public function test_admin_can_render_deposit_review_and_seller_can_upload_a_receiving_qr(): void
    {
        $this->actingAs($this->seller)->post(route('seller.account.gcash'), ['gcash_account_name' => 'GCash Shop', 'gcash_qr' => $this->image()])->assertRedirect()->assertSessionHasNoErrors();
        $seller = $this->seller->seller()->first();
        Storage::disk('public')->assertExists($seller->gcash_qr_path);
        $this->assertSame('GCash Shop', $seller->gcash_account_name);
        $this->submit();
        $this->actingAs($this->admin)->get(route('admin.gcash.index'))->assertOk()->assertSee('Verify actual incoming funds')->assertSee('TEST-ORDER-');
    }

    public function test_another_rider_cannot_accept_the_payout(): void
    {
        $this->fund();
        $rider = $this->user('rider');
        $profile = Rider::create(['user_id' => $rider->id, 'name' => 'Assigned rider']);
        $payout = EscrowRemittance::where('recipient_role', 'last_mile_rider')->firstOrFail();
        $payout->update(['recipient_user_id' => $rider->id, 'rider_id' => $profile->id, 'status' => 'remitted']);
        $other = $this->user('rider');
        Rider::create(['user_id' => $other->id, 'name' => 'Other rider']);
        Sanctum::actingAs($other, ['rider']);
        $this->postJson('/api/logistics/rider/remittances/'.$payout->id.'/accept-payout')->assertNotFound();
        $this->assertSame('remitted', $payout->fresh()->status);
        $this->assertDatabaseCount('rider_earnings', 0);
    }

    public function test_one_escrow_transfer_reference_cannot_fund_two_orders(): void
    {
        $deposit = $this->submit();
        $other = Order::create(['seller_id' => $this->seller->id, 'buyer_id' => $this->buyer->id, 'status' => 'pending', 'total_amount' => 150]);
        OrderItem::create(['order_id' => $other->id, 'seller_id' => $this->seller->id, 'quantity' => 1, 'price' => 100]);
        Payment::create(['order_id' => $other->id, 'method' => 'gcash', 'amount' => 150, 'status' => 'pending', 'proof_path' => 'payment-proofs/buyer.png']);
        GcashEscrowService::verifyReceipt($other, $this->seller);
        $filesBefore = Storage::disk('private')->allFiles();
        $this->actingAs($this->seller)->post(route('seller.gcash.deposit', $other), ['amount' => 150, 'reference' => strtolower($deposit->reference), 'escrow_proof' => $this->image()])->assertSessionHasErrors('reference');
        $this->assertSame($filesBefore, Storage::disk('private')->allFiles());
        $this->assertSame('receipt_verified', GcashEscrowDeposit::where('order_id', $other->id)->first()->status);
    }

    public function test_admin_cannot_release_more_than_the_confirmed_escrow_balance(): void
    {
        $this->fund();
        $this->order->delivery->update(['status' => 'delivered']);
        $row = EscrowRemittance::where('recipient_role', 'seller')->firstOrFail();
        $row->update(['status' => 'pending_remittance', 'amount' => 151]);
        $this->actingAs($this->admin)->post(route('admin.escrow.remit', $row), ['bank_reference' => 'OVERDRAW-ATTEMPT'])->assertSessionHasErrors('gcash');
        $this->assertSame('pending_remittance', $row->fresh()->status);
    }

    public function test_gcash_pickup_pay_waits_for_remittance_acceptance_too(): void
    {
        $this->fund();
        $user = $this->user('rider');
        $rider = Rider::create(['user_id' => $user->id, 'name' => 'Pickup rider']);
        $delivery = $this->order->delivery;
        $delivery->update(['sorting_center_handoff_rider_id' => $rider->id, 'rider_id' => $rider->id, 'received_at' => now()]);
        $this->assertNull(EscrowEarnings::creditPickupRiderOnHubReceipt($delivery));
        $this->assertDatabaseCount('rider_earnings', 0);
        $delivery->update(['status' => 'delivered']);
        Remittances::ensureForDeliveryOrFail($delivery);
        $payout = EscrowRemittance::where('recipient_role', 'pickup_rider')->firstOrFail();
        $this->assertSame('pending_remittance', $payout->status);
        $this->actingAs($this->admin)->post(route('admin.escrow.remit', $payout), ['bank_reference' => 'PICKUP-PAYOUT-001'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('rider_earnings', 0);
        $this->assertTrue($payout->fresh()->accept($user));
        $this->assertSame(30.0, (float) RiderEarning::sum('amount'));
        $this->assertDatabaseHas('rider_earnings', ['delivery_id' => $delivery->id, 'type' => 'pickup', 'amount' => 30]);
    }

    public function test_cod_earnings_remain_visible_without_escrow_acceptance(): void
    {
        $this->order->payment()->update(['method' => 'cash_on_delivery']);
        $user = $this->user('rider');
        $rider = Rider::create(['user_id' => $user->id, 'name' => 'COD rider']);
        RiderEarning::create(['rider_id' => $rider->id, 'delivery_id' => $this->order->delivery->id, 'type' => 'delivery', 'amount' => 50, 'earned_on' => now()->toDateString()]);
        $this->assertSame(50.0, (float) RiderEarning::where('rider_id', $rider->id)->sum('amount'));
        $this->assertDatabaseCount('escrow_remittances', 0);
    }
}
