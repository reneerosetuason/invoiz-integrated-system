<?php

namespace Tests\Feature;

use App\Models\{Order, Payment, Product, ReturnRequest, ReturnWorkflow, User, Rider, Delivery, EscrowRemittance, GcashEscrowDeposit};
use App\Services\{ReturnWorkflowService, GcashEscrowService};
use App\Support\{Payments, EscrowEarnings};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB, Auth, Storage};
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;

/** Uses the same isolated checkout/escrow fixtures as the forward-payment suite. */
class ReturnWorkflowTest extends GcashEscrowWorkflowTest
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('return_requests', function (Blueprint $t) {
            $t->text('decision_note')->nullable(); $t->timestamp('resolved_at')->nullable();
            $t->string('evidence_path')->nullable(); $t->string('evidence_type')->nullable();
        });
        Schema::table('payment_disputes', fn (Blueprint $t) => $t->timestamp('resolved_at')->nullable());
        (require database_path('migrations/2026_11_05_180000_create_return_workflows.php'))->up();
        Schema::create('logistics_centers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->unsignedBigInteger('logistics_company_id'); $t->timestamps();
        });
        DB::table('logistics_centers')->insert([
            ['id' => 1, 'name' => 'Buyer hub', 'logistics_company_id' => 1],
            ['id' => 2, 'name' => 'Seller hub', 'logistics_company_id' => 1],
            ['id' => 3, 'name' => 'Foreign hub', 'logistics_company_id' => 2],
        ]);
    }

    private function actor(string $role, int $center = 1): User
    {
        return User::create(['name' => $role, 'email' => uniqid().'@test.local', 'password' => 'Password123!',
            'role' => $role, 'status' => 'active', 'account_status' => 'active', 'approval_status' => 'approved',
            'email_verified_at' => now(), 'center_id' => $center]);
    }

    private function scenario(): array
    {
        $order = Order::firstOrFail(); $seller = User::where('role', 'seller')->firstOrFail();
        $buyer = User::where('role', 'buyer')->firstOrFail(); $admin = User::where('is_admin', true)->firstOrFail();
        Payment::where('order_id', $order->id)->update(['status' => 'paid']);
        GcashEscrowDeposit::create(['order_id' => $order->id, 'seller_id' => $seller->id, 'amount' => 150, 'status' => 'funded', 'confirmed_at' => now()]);
        $order->update(['payment_status' => 'paid']);
        Delivery::first()->update(['status' => 'delivered', 'center_id' => 1, 'delivered_at' => now()]);
        $order->update(['status' => 'delivered']);
        $product = Product::create(['seller_id' => $seller->id, 'name' => 'Returned item', 'price' => 100, 'stock' => 10, 'status' => 'active']);
        $item = $order->items()->first(); $item->update(['product_id' => $product->id]);
        Payments::settleForDelivery($order);
        $case = ReturnRequest::create(['order_id' => $order->id, 'order_item_id' => $item->id, 'buyer_id' => $buyer->id,
            'seller_id' => $seller->id, 'product_id' => $product->id, 'quantity' => 1, 'reason' => 'damaged', 'status' => 'approved', 'refund_amount' => 100]);
        ReturnWorkflowService::start($case);
        $staff = $this->actor('staff'); $riderUser = $this->actor('rider');
        $rider = Rider::create(['user_id' => $riderUser->id, 'name' => 'Return rider', 'center_id' => 1, 'status' => 'available', 'is_verified' => true, 'approved_at' => now()]);
        return compact('order', 'seller', 'buyer', 'admin', 'product', 'case', 'staff', 'riderUser', 'rider');
    }

    private function received(array $f, bool $restock = false): void
    {
        ReturnWorkflowService::change($f['case'], $f['staff'], 'assign', ['rider_id' => $f['rider']->id]);
        ReturnWorkflowService::change($f['case'], $f['riderUser'], 'pickup');
        ReturnWorkflowService::change($f['case'], $f['staff'], 'intake');
        ReturnWorkflowService::change($f['case'], $f['staff'], 'dispatch_seller', ['reference' => 'RTS-TEST']);
        ReturnWorkflowService::change($f['case'], $f['seller'], 'receive', ['restock' => $restock]);
    }

    private function refundData(float $recovery = 60): array
    {
        return ['reference' => 'BUYER-REFUND-1', 'proof_path' => 'refund.png', 'recovery_amount' => $recovery,
            'recovery_reference' => 'RECOVER-1', 'recovery_proof_path' => 'recovery.png'];
    }

    public function test_reverse_custody_refund_and_buyer_confirmation_are_separate_and_replay_safe(): void
    {
        $f = $this->scenario(); $this->received($f);
        $this->assertSame('item_received', $f['case']->fresh()->status);
        $this->assertSame('paid', $f['order']->fresh()->payment_status);
        $this->assertSame(10, (int) $f['product']->fresh()->stock);
        ReturnWorkflowService::change($f['case'], $f['admin'], 'refund', $this->refundData());
        $this->assertSame('item_received', $f['case']->fresh()->status);
        $this->assertSame('refund_sent', $f['case']->fresh()->workflow->stage);
        $this->assertSame(100.0, (float) Payments::transactionFor($f['order'])->refunded_amount);
        $this->assertTrue(GcashEscrowService::funded($f['order']->fresh()));
        ReturnWorkflowService::change($f['case'], $f['buyer'], 'confirm_refund');
        ReturnWorkflowService::change($f['case'], $f['buyer'], 'confirm_refund');
        $this->assertSame('refunded', $f['case']->fresh()->status);
        $this->assertCount(7, $f['case']->fresh()->workflow->events);
        Payments::settleForDelivery($f['order']->fresh(), true);
        $this->assertSame('partially_refunded', $f['order']->fresh()->payment_status);
        $this->assertSame(0.0, (float) Payments::transactionFor($f['order'])->platform_commission);
        $this->assertDatabaseCount('rider_earnings', 0);
    }

    public function test_refund_requires_actual_transfer_and_sufficient_recovery_to_preserve_logistics_fees(): void
    {
        $f = $this->scenario(); $this->received($f);
        foreach ([[], ['reference' => 'NO-PROOF'], $this->refundData(0)] as $data) {
            try { ReturnWorkflowService::change($f['case'], $f['admin'], 'refund', $data); $this->fail('Unsafe refund allowed.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('return', $e->errors()); }
        }
        $this->assertSame(0.0, (float) Payments::transactionFor($f['order'])->refunded_amount);
        $this->assertNull($f['case']->fresh()->workflow->refund_sent_at);
    }

    public function test_seller_cannot_receive_before_logistics_dispatch_and_restock_is_explicit_once(): void
    {
        $f = $this->scenario();
        try { ReturnWorkflowService::change($f['case'], $f['seller'], 'receive', ['restock' => true]); $this->fail('Skipped custody.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('return', $e->errors()); }
        $this->received($f, true);
        $this->assertSame(11, (int) $f['product']->fresh()->stock);
        try { ReturnWorkflowService::change($f['case'], $f['seller'], 'receive', ['restock' => true]); $this->fail('Repeated stock restore.'); }
        catch (ValidationException $e) { $this->assertSame(11, (int) $f['product']->fresh()->stock); }
    }

    public function test_other_hubs_riders_sellers_and_buyers_cannot_change_custody_or_confirm_refunds(): void
    {
        $f = $this->scenario();
        foreach ([[$this->actor('staff', 3), 'assign', ['rider_id' => $f['rider']->id]],
            [$this->actor('rider'), 'pickup', []], [$this->actor('seller'), 'receive', []],
            [$this->actor('buyer'), 'confirm_refund', []], [$f['seller'], 'refund', $this->refundData()]] as [$actor, $action, $data]) {
            try { ReturnWorkflowService::change($f['case'], $actor, $action, $data); $this->fail('Foreign actor allowed.'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        }
    }

    public function test_return_transfer_requires_destination_hub_receipt_before_seller_dispatch(): void
    {
        $f = $this->scenario();
        ReturnWorkflowService::change($f['case'], $f['staff'], 'assign', ['rider_id' => $f['rider']->id]);
        ReturnWorkflowService::change($f['case'], $f['riderUser'], 'pickup');
        ReturnWorkflowService::change($f['case'], $f['staff'], 'intake');
        ReturnWorkflowService::change($f['case'], $f['staff'], 'transfer', ['destination_center_id' => 2, 'reference' => 'RETURN-MANIFEST']);
        $destination = $this->actor('staff', 2);
        ReturnWorkflowService::change($f['case'], $destination, 'receive_transfer');
        ReturnWorkflowService::change($f['case'], $destination, 'dispatch_seller', ['reference' => 'SELLER-LEG']);
        $this->assertSame(2, (int) $f['case']->fresh()->workflow->center_id);
        $this->assertSame('sent_to_seller', $f['case']->fresh()->workflow->stage);
        $this->assertSame('delivered', Delivery::first()->status);
    }

    public function test_open_return_blocks_admin_payout_and_private_refund_proof_is_buyer_scoped(): void
    {
        $f = $this->scenario();
        $row = EscrowRemittance::where('recipient_role', 'seller')->firstOrFail();
        $this->actingAs($f['admin'])->post('/admin/payments/escrow-remittances/'.$row->id.'/remit', ['bank_reference' => 'BLOCKED'])
            ->assertSessionHasErrors();
        Storage::disk('private')->put('refund.png', 'private receipt');
        $this->received($f); ReturnWorkflowService::change($f['case'], $f['admin'], 'refund', $this->refundData());
        $this->actingAs($f['seller'])->get('/return-center/'.$f['case']->id.'/proof')->assertForbidden();
        $this->actingAs($f['buyer'])->get('/return-center/'.$f['case']->id.'/proof')->assertOk();
        $this->assertArrayNotHasKey('refund_proof_path', $f['case']->fresh()->progress);
    }

    public function test_refund_confirm_api_requires_the_owning_buyer_and_admin_page_renders(): void
    {
        $f = $this->scenario(); $this->received($f);
        ReturnWorkflowService::change($f['case'], $f['admin'], 'refund', $this->refundData());
        $this->actingAs($f['admin'])->get('/return-center')->assertOk()->assertSee('Return center')->assertSee('BUYER-REFUND-1');
        Auth::forgetGuards(); Sanctum::actingAs($f['buyer']);
        $this->postJson('/api/returns/'.$f['case']->id.'/confirm')->assertOk()->assertJsonPath('return.status', 'refunded');
    }

    public function test_real_action_routes_accept_handoffs_but_require_admin_transfer_attestation(): void
    {
        $f = $this->scenario();
        $this->actingAs($f['staff'])->postJson('/return-center/'.$f['case']->id, ['action' => 'assign', 'rider_id' => $f['rider']->id])->assertOk();
        $this->actingAs($f['riderUser'])->postJson('/return-center/'.$f['case']->id, ['action' => 'pickup'])->assertOk();
        $this->actingAs($f['staff'])->postJson('/return-center/'.$f['case']->id, ['action' => 'intake'])->assertOk();
        $this->actingAs($f['staff'])->postJson('/return-center/'.$f['case']->id, ['action' => 'dispatch_seller', 'reference' => 'RTS'])->assertOk();
        $this->actingAs($f['seller'])->postJson('/return-center/'.$f['case']->id, ['action' => 'receive'])->assertOk();
        $this->actingAs($f['admin'])->postJson('/return-center/'.$f['case']->id, ['action' => 'refund', 'reference' => 'PAYMENT'])
            ->assertUnprocessable()->assertJsonValidationErrors('confirm_transfer');
        $photo = fn () => UploadedFile::fake()->createWithContent('proof.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a5X8AAAAASUVORK5CYII='));
        $this->actingAs($f['admin'])->postJson('/return-center/'.$f['case']->id, ['action' => 'refund', 'reference' => 'PAYMENT',
            'confirm_transfer' => 1, 'proof' => $photo(), 'recovery_amount' => 60, 'recovery_reference' => 'INCOMING', 'recovery_proof' => $photo()])->assertOk();
        $flow = $f['case']->fresh()->workflow;
        Storage::disk('private')->assertExists($flow->refund_proof_path);
        $this->actingAs($f['buyer'])->postJson('/return-center/'.$f['case']->id, ['action' => 'confirm_refund'])->assertOk();
    }

    public function test_cod_returns_wait_for_reconciliation_and_do_not_erase_refunds_on_later_settlement(): void
    {
        $f = $this->scenario();
        $f['order']->payment->update(['method' => 'cash_on_delivery']);
        Delivery::first()->update(['payment_method' => 'cash_on_delivery']);
        $this->received($f);
        $tx = Payments::transactionFor($f['order']); $tx->update(['status' => 'pending']);
        try { ReturnWorkflowService::change($f['case'], $f['admin'], 'refund', $this->refundData(100)); $this->fail('Unreconciled cash refunded.'); }
        catch (ValidationException $e) { $this->assertArrayHasKey('return', $e->errors()); }
        $tx->update(['status' => 'successful']);
        ReturnWorkflowService::change($f['case'], $f['admin'], 'refund', $this->refundData(100));
        Payments::settleForDelivery($f['order']->fresh(), true);
        $this->assertSame('partially_refunded', $f['order']->fresh()->payment_status);
        $this->assertSame(100.0, (float) $tx->fresh()->refunded_amount);
    }

    public function test_prepaid_cancellation_queues_the_unused_shipping_too_without_claiming_a_refund(): void
    {
        $order = Order::firstOrFail(); $order->update(['status' => 'cancelled']);
        Payments::voidForCancel($order);
        $case = ReturnRequest::firstOrFail();
        $this->assertSame('item_received', $case->status);
        $this->assertSame(150.0, (float) $case->refund_amount);
        $this->assertSame('refund_pending', $case->workflow->stage);
        $this->assertSame('pending', $order->fresh()->payment->status);
        $this->assertSame(0.0, (float) Payments::transactionFor($order)->refunded_amount);
        Payments::voidForCancel($order); $this->assertDatabaseCount('return_requests', 1);
    }
}
