<?php

namespace Tests\Feature;

use App\Models\{Order, Payment, ReturnRequest, ReturnSettlement, ReturnAllocation, User, Rider, Delivery, GcashEscrowDeposit};
use App\Services\{ReturnSettlementService as Settlements, ReturnWorkflowService};
use App\Support\Payments;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB, Storage};
use Illuminate\Validation\ValidationException;

class ReturnSettlementTest extends GcashEscrowWorkflowTest
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
        (require database_path('migrations/2026_11_06_180000_create_return_settlement_ledgers.php'))->up();
        Schema::create('logistics_centers', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->boolean('is_active')->default(true); $t->unsignedBigInteger('logistics_company_id'); $t->timestamps();
        });
        DB::table('logistics_centers')->insert([['id'=>1,'name'=>'Return hub','is_active'=>true,'logistics_company_id'=>1], ['id'=>2,'name'=>'Other hub','is_active'=>true,'logistics_company_id'=>1]]);
        Storage::fake('private'); Storage::disk('private')->put('test-proof.png', 'fixture');
    }

    private function fixture(): array
    {
        $order = Order::firstOrFail();
        $seller = User::where('role','seller')->firstOrFail(); $buyer = User::where('role','buyer')->firstOrFail();
        $admin = User::where('is_admin',true)->firstOrFail();
        Payment::where('order_id',$order->id)->update(['status'=>'paid']);
        GcashEscrowDeposit::create(['order_id'=>$order->id,'seller_id'=>$seller->id,'amount'=>150,'status'=>'funded','confirmed_at'=>now()]);
        $order->update(['payment_status'=>'paid']);
        Delivery::first()->update(['status'=>'delivered','center_id'=>1,'delivered_at'=>now()]);
        $order->update(['payment_status'=>'paid','status'=>'delivered']); Payments::settleForDelivery($order);
        $product = \App\Models\Product::create(['seller_id'=>$seller->id,'name'=>'Replacement fixture','price'=>100,'stock'=>10,'status'=>'active']);
        $item = $order->items()->first(); $item->update(['product_id'=>$product->id]);
        $case = ReturnRequest::create(['order_id'=>$order->id,'order_item_id'=>$item->id,'buyer_id'=>$buyer->id,'seller_id'=>$seller->id,
            'product_id'=>$item->product_id,'quantity'=>1,'reason'=>'wrong_item','status'=>'requested','refund_amount'=>100]);
        Settlements::ensure($case);
        $staff = User::create(['name'=>'Return staff','email'=>uniqid().'@test.local','password'=>'test','role'=>'staff','center_id'=>1,'account_status'=>'active','status'=>'active','email_verified_at'=>now()]);
        $riderUser = User::create(['name'=>'Return rider','email'=>uniqid().'@test.local','password'=>'test','role'=>'rider','center_id'=>1,'account_status'=>'active','status'=>'active','email_verified_at'=>now()]);
        $rider = Rider::create(['user_id'=>$riderUser->id,'name'=>'Return rider','center_id'=>1,'status'=>'available','is_verified'=>true,'approved_at'=>now()]);
        return compact('order','seller','buyer','admin','case','staff','rider','riderUser');
    }

    private function change(array $f, string $actor, string $action, array $data = []): void
    {
        Settlements::change($f['case'], $f[$actor], $action, ['version'=>$f['case']->fresh()->settlement->version]+$data);
    }

    private function proposal(array $f, string $resolution = 'refund_only', string $payer = 'seller'): void
    {
        $this->change($f,'seller','propose',['fault'=>$payer === 'buyer' ? 'buyer' : 'seller','resolution'=>$resolution,'payer'=>$payer,
            'funding_method'=>$payer === 'buyer' ? 'deduct_refund' : 'deposit','merchandise_amount'=>$resolution === 'exchange' ? 0 : 100,'note'=>'Compared purchased variant and the submitted buyer evidence.', 'replacement_description'=>'Agreed replacement in the correct size and color']);
        if (in_array($resolution,['return_refund','exchange'])) {
            $lines = [['role'=>'collection_rider','amount'=>10],['role'=>'collection_hub','amount'=>5],['role'=>'seller_delivery_rider','amount'=>5]];
            if ($resolution === 'exchange') $lines[] = ['role'=>'replacement_delivery_rider','amount'=>5];
            $this->change($f,'admin','quote',['collection_center_id'=>1,'seller_center_id'=>1,'allocations'=>$lines]);
        }
        $this->change($f,'seller','accept'); $this->change($f,'buyer','accept');
        $this->change($f,'admin','decide',['note'=>'Admin reviewed the evidence, responsibility and all quoted charges.']);
    }

    private function deposit(array $f, string $purpose, float $amount): void
    {
        $p = Settlements::submitPayment($f['case'],$f['seller'],['version'=>$f['case']->fresh()->settlement->version,'purpose'=>$purpose,
            'amount'=>$amount,'reference'=>uniqid('BANK-'),'proof_path'=>'test-proof.png']);
        Settlements::reviewPayment($p,$f['admin'],true,'Verified the actual incoming bank transaction and account.');
    }

    private function fund(array $f): void
    {
        $s=$f['case']->fresh()->settlement;
        if ($s->net_refund_cents > 0) $this->change($f,'buyer','destination',['provider'=>'Test wallet','holder'=>'Buyer','account'=>'09170000000']);
        if ($s->funding_method === 'deposit' && $s->budgetCents()>0) $this->deposit($f,'return_budget',$s->budgetCents()/100);
        $need=ReturnWorkflowService::fundingSummary($f['order'],($s->net_refund_cents+($s->funding_method==='deduct_refund'?$s->budgetCents():0))/100)['shortfall'];
        if ($need>0) $this->deposit($f,'refund_recovery',$need);
        if ($s->funding_method==='deduct_refund') $this->change($f,'admin','reserve');
        $this->change($f,'admin','ready');
    }

    private function physical(array $f): void
    {
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        ReturnWorkflowService::change($f['case'],$f['staff'],'assign',['rider_id'=>$f['rider']->id]);
        ReturnWorkflowService::change($f['case'],$f['riderUser'],'pickup',['proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['staff'],'intake');
        ReturnWorkflowService::change($f['case'],$f['staff'],'dispatch_seller',['reference'=>'RETURN-HANDOVER','rider_id'=>$f['rider']->id]);
        ReturnWorkflowService::change($f['case'],$f['seller'],'receive');
    }

    public function test_refund_only_never_fakes_a_physical_return_or_creates_transport_work(): void
    {
        $f=$this->fixture();$this->proposal($f);$this->fund($f);
        $this->assertSame('approved',$f['case']->fresh()->status);
        $this->assertSame('refund_pending',$f['case']->fresh()->workflow->stage);
        $this->assertDatabaseCount('return_allocations',0);
        ReturnWorkflowService::change($f['case'],$f['admin'],'refund',['reference'=>'BUYER-REFUND','proof_path'=>'test-proof.png']);
        ReturnWorkflowService::change($f['case'],$f['buyer'],'confirm_refund');
        $this->assertSame('refunded_return_waived',$f['case']->fresh()->settlement->status);
        $this->assertFalse((bool)$f['case']->fresh()->workflow->stock_restored);
        $this->assertEquals(100,Payments::transactionFor($f['order']->fresh())->refunded_amount);
    }

    public function test_buyer_deduction_is_reserved_once_and_net_refund_reverses_the_full_merchandise(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund','buyer');$this->fund($f);
        $s=$f['case']->fresh()->settlement;
        $this->assertSame(2000,(int)$s->reserved_cents);$this->assertSame(8000,(int)$s->net_refund_cents);
        $this->physical($f);
        ReturnWorkflowService::change($f['case'],$f['admin'],'refund',['reference'=>'NET-REFUND','proof_path'=>'test-proof.png']);
        $this->assertEquals(80,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertEquals(100,$f['case']->fresh()->merchandiseRefundAmount());
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->platform_commission);
    }

    public function test_return_earnings_require_verified_work_actual_remittance_and_recipient_acceptance(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);$this->physical($f);
        $line=ReturnAllocation::where('role','collection_rider')->firstOrFail();
        $this->assertSame('eligible',$line->status);
        Settlements::payout($line,$f['riderUser'],'destination',['provider'=>'Bank','holder'=>'Rider','account'=>'123456789']);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'RETURN-PAYOUT','proof_path'=>'test-proof.png']);
        $this->assertSame(0,ReturnAllocation::where('status','accepted')->sum('amount_cents'));
        Settlements::payout($line,$f['riderUser'],'accept',[]);
        Settlements::payout($line,$f['riderUser'],'accept',[]);
        $this->assertSame(1000,(int)ReturnAllocation::where('status','accepted')->sum('amount_cents'));
        $this->assertDatabaseCount('rider_earnings',0);
    }

    public function test_screenshots_cannot_unlock_pickup_and_nonadmins_cannot_verify_funding(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');
        $p=Settlements::submitPayment($f['case'],$f['seller'],['version'=>$f['case']->fresh()->settlement->version,'purpose'=>'return_budget','amount'=>20,'reference'=>'PENDING-RECEIPT','proof_path'=>'test-proof.png']);
        try {$this->change($f,'admin','ready');$this->fail('Screenshot unlocked funding.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->actingAs($f['buyer'])->post('/return-deposits/'.$p->id.'/review',['decision'=>'verify','note'=>'A buyer cannot approve their own receipt.','confirm'=>1])->assertForbidden();
    }

    public function test_private_refund_account_is_encrypted_and_hidden_from_seller_and_strangers(): void
    {
        $f=$this->fixture();$this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $raw=DB::table('return_settlements')->where('return_request_id',$f['case']->id)->value('refund_destination');
        $this->assertStringNotContainsString('123456789',$raw);
        $this->actingAs($f['seller'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()->assertJsonPath('refund_destination',null);
        $this->actingAs($f['buyer'])->getJson('/return-center/'.$f['case']->id.'/agreement')->assertOk()->assertJsonPath('refund_destination.account','123456789');
    }

    public function test_stale_quotes_and_buyer_charges_for_seller_fault_are_rejected(): void
    {
        $f=$this->fixture();$this->proposal($f);
        try {Settlements::change($f['case'],$f['buyer'],'accept',['version'=>1]);$this->fail('Accepted stale quote.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        try {$this->change($f,'seller','propose',['fault'=>'seller','resolution'=>'return_refund','payer'=>'buyer','merchandise_amount'=>100,'note'=>'Attempt to charge buyer for seller mistake.']);$this->fail('Charged buyer.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
    }

    public function test_exchange_has_no_cash_refund_and_waits_for_actual_replacement_receipt(): void
    {
        $f=$this->fixture();$this->proposal($f,'exchange');$this->fund($f);$this->physical($f);
        $this->assertSame('item_received',$f['case']->fresh()->status);
        $this->change($f,'staff','replacement_dispatch',['reference'=>'REPLACEMENT-001','proof_path'=>'test-proof.png','rider_id'=>$f['rider']->id]);
        $this->assertSame('reserved',ReturnAllocation::where('role','replacement_delivery_rider')->value('status'));
        $this->change($f,'buyer','replacement_received');
        $this->assertSame('eligible',ReturnAllocation::where('role','replacement_delivery_rider')->value('status'));
        $this->assertSame('exchange_completed',$f['case']->fresh()->settlement->status);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
    }

    public function test_cancelled_deduction_releases_original_reservation_without_giving_buyer_free_money(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund','buyer');$this->fund($f);
        $this->change($f,'admin','close_unused',['note'=>'Buyer and seller cancelled before physical collection.']);
        $this->assertEquals(0,$f['case']->fresh()->settlement->reserved_cents);
        $this->assertDatabaseMissing('return_allocations',['role'=>'unused_budget_refund']);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
        $this->assertSame('cancelled',$f['case']->fresh()->settlement->status);
    }

    public function test_cancelled_separate_payment_returns_budget_to_the_payer_without_counting_it_as_earnings(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);
        $this->change($f,'admin','close_unused',['note'=>'Cancelled by agreement before any collection work occurred.']);
        $line=ReturnAllocation::where('role','unused_budget_refund')->firstOrFail();
        $this->assertEquals($f['seller']->id,$line->recipient_user_id);$this->assertEquals(2000,$line->amount_cents);
        Settlements::payout($line,$f['seller'],'destination',['provider'=>'Bank','holder'=>'Seller','account'=>'123456789']);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'UNUSED-BUDGET','proof_path'=>'test-proof.png']);
        Settlements::payout($line,$f['seller'],'accept',[]);
        $this->actingAs($f['seller'])->getJson('/return-remittances')->assertOk()->assertJsonPath('accepted_earnings_cents',0);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
    }

    public function test_funding_activation_is_not_replayed_after_receipt_and_disputes_stop_new_custody(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);
        try {$this->change($f,'admin','ready');$this->fail('Activated twice.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->change($f,'buyer','dispute',['note'=>'The promised replacement and charges need admin review.']);
        try {$this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);$this->fail('Scheduled disputed return.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->change($f,'admin','decide',['note'=>'The parties clarified the issue; the agreed financial terms stay unchanged.']);
        $this->assertSame('funded',$f['case']->fresh()->settlement->status);
        $this->change($f,'seller','schedule',['collection_at'=>now()->addDay()->toDateTimeString()]);
        $this->assertSame('collection_scheduled',$f['case']->fresh()->settlement->status);
    }

    public function test_agreement_and_remittance_pages_render_for_each_participant_without_bank_leaks(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');
        $this->change($f,'buyer','destination',['provider'=>'Private bank','holder'=>'Buyer','account'=>'SECRET-123456789']);
        foreach (['seller','buyer','admin','staff'] as $actor) {
            $response=$this->actingAs($f[$actor])->withSession(['active_role'=>$actor==='seller'?'seller':'buyer'])->get('/return-center/'.$f['case']->id.'/agreement');
            $response->assertOk()->assertSee('Return agreement');
            if (!in_array($actor,['buyer','admin'])) $response->assertDontSee('SECRET-123456789');
            if (getenv('RETURN_UI_PREVIEW')) file_put_contents(base_path('.tools/return-agreement-'.$actor.'.html'),$response->getContent());
        }
        $response=$this->actingAs($f['admin'])->get('/return-remittances');$response->assertOk()->assertSee('Your confirmed return service earnings');
        if (getenv('RETURN_UI_PREVIEW')) file_put_contents(base_path('.tools/return-remittances-admin.html'),$response->getContent());
    }

    public function test_rejected_receipt_can_be_corrected_without_reusing_verified_money(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');
        $data=['version'=>$f['case']->fresh()->settlement->version,'purpose'=>'return_budget','amount'=>20,'reference'=>'CORRECTED-TRANSFER','proof_path'=>'test-proof.png'];
        $p=Settlements::submitPayment($f['case'],$f['seller'],$data);
        Settlements::reviewPayment($p,$f['admin'],false,'Receipt image was unreadable; check and resubmit the same transfer.');
        $corrected=Settlements::submitPayment($f['case'],$f['seller'],$data);
        $this->assertEquals($p->id,$corrected->id);
        Settlements::reviewPayment($corrected,$f['admin'],true,'Actual incoming transfer verified in the platform bank account.');
        $this->assertDatabaseCount('return_fund_payments',1);
        $this->assertEquals(2000,Settlements::poolCents($f['case']->fresh()->settlement));
    }

    public function test_unused_recovery_is_returned_once_without_creating_refund_credit(): void
    {
        $f=$this->fixture();$this->proposal($f);
        \App\Models\EscrowRemittance::where('order_id',$f['order']->id)->update(['status'=>'remitted']);
        $this->assertEquals(100,ReturnWorkflowService::fundingSummary($f['order'],100)['shortfall']);
        $this->deposit($f,'refund_recovery',100);
        $this->change($f,'admin','close_unused',['note'=>'Cancelled before activation; return the verified unused recovery.']);
        $line=ReturnAllocation::whereNotNull('fund_payment_id')->firstOrFail();
        $this->assertEquals(10000,$line->amount_cents);
        Settlements::payout($line,$f['seller'],'destination',['provider'=>'Bank','holder'=>'Seller','account'=>'123456789']);
        Settlements::payout($line,$f['admin'],'remit',['reference'=>'UNUSED-RECOVERY','proof_path'=>'test-proof.png']);
        $this->assertEquals(10000,\App\Models\ReturnFundPayment::first()->returned_cents);
        $this->assertEquals(0,ReturnWorkflowService::fundingSummary($f['order'],0)['available']);
        $this->change($f,'admin','close_unused',['note'=>'Rechecking a reconciled cancellation must not duplicate money.']);
        $this->assertDatabaseCount('return_allocations',1);
        $this->assertEquals(0,Payments::transactionFor($f['order']->fresh())->refunded_amount);
    }

    public function test_unreconciled_original_payment_cannot_unlock_a_resolution(): void
    {
        $f=$this->fixture();$this->proposal($f);
        GcashEscrowDeposit::where('order_id',$f['order']->id)->update(['status'=>'submitted','confirmed_at'=>null]);
        $this->change($f,'buyer','destination',['provider'=>'Bank','holder'=>'Buyer','account'=>'123456789']);
        $this->expectException(ValidationException::class);
        $this->change($f,'admin','ready');
    }

    public function test_exchange_stock_is_reserved_once_and_restored_when_cancelled_before_collection(): void
    {
        $f=$this->fixture();$this->proposal($f,'exchange');$this->fund($f);
        $product=$f['case']->product;
        $this->assertEquals(9,$product->fresh()->stock);
        try {$this->change($f,'admin','ready');$this->fail('Reserved the replacement twice.');}catch(ValidationException $e){$this->assertArrayHasKey('return',$e->errors());}
        $this->assertEquals(9,$product->fresh()->stock);
        $this->change($f,'admin','close_unused',['note'=>'Agreed exchange cancelled before any collection occurred.']);
        $this->assertEquals(10,$product->fresh()->stock);
        $this->change($f,'admin','close_unused',['note'=>'Reconciliation must never restore replacement stock twice.']);
        $this->assertEquals(10,$product->fresh()->stock);
    }

    public function test_recipient_bank_details_and_acceptance_cannot_be_changed_by_another_participant(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);$this->physical($f);
        $line=ReturnAllocation::where('role','collection_rider')->firstOrFail();
        $this->actingAs($f['seller'])->postJson('/return-remittances/'.$line->id,[
            'action'=>'destination','confirm'=>1,'provider'=>'Wrong bank','holder'=>'Seller','account'=>'123456789',
        ])->assertForbidden();
        $this->actingAs($f['buyer'])->postJson('/return-remittances/'.$line->id,['action'=>'accept','confirm'=>1])->assertForbidden();
        $this->assertSame('eligible',$line->fresh()->status);
    }

    public function test_existing_in_transit_legacy_returns_are_not_retroactively_quoted(): void
    {
        $f=$this->fixture();$f['case']->settlement()->delete();
        $f['case']->workflow()->update(['stage'=>'in_transit']);
        $this->assertNull(Settlements::ensure($f['case']->fresh()));
        $this->assertFalse($f['case']->fresh()->has_return_agreement);
        $this->assertDatabaseCount('return_settlements',0);
    }

    public function test_pickup_form_uses_philippine_time_instead_of_server_time(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->fund($f);
        $pickup=now()->addDay()->timezone('Asia/Manila')->startOfMinute();
        $this->actingAs($f['seller'])->post('/return-center/'.$f['case']->id.'/agreement',[
            'action'=>'schedule','version'=>$f['case']->fresh()->settlement->version,'collection_at'=>$pickup->format('Y-m-d\TH:i'),'confirm'=>1,
        ])->assertSessionHasNoErrors();
        $this->assertSame($pickup->timestamp,$f['case']->fresh()->settlement->collection_at->timestamp);
    }

    public function test_legacy_seller_reject_action_cannot_close_a_case_holding_verified_return_funds(): void
    {
        $f=$this->fixture();$this->proposal($f,'return_refund');$this->deposit($f,'return_budget',20);
        $this->actingAs($f['seller'])->withSession(['active_role'=>'seller'])->post(route('seller.returns.update',$f['case']->id),[
            'action'=>'reject','note'=>'Seller disputes this case after a verified incoming payment.',
        ])->assertRedirect(route('returns.agreement',$f['case']));
        $this->assertSame('requested',$f['case']->fresh()->status);
        $this->assertSame('disputed',$f['case']->fresh()->settlement->status);
        $this->assertEquals(2000,Settlements::poolCents($f['case']->fresh()->settlement));
    }
}
